<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Integration;
use App\Services\TokenRefreshService;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDriveService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncIntegrationFilesJob implements ShouldQueue
{
    use Queueable;

    private const int CHUNK_SIZE = 500;

    public $timeout = 900;

    public $tries = 3;

    public function __construct(protected Integration $integration)
    {
        $this->onQueue('sync');
    }

    public function handle(TokenRefreshService $tokenService): void
    {
        try {
            $accessToken = $tokenService->getValidToken($this->integration);
            $provider = $this->integration->provider->value;

            if ($provider === 'google') {
                $this->syncWithGoogleSdkBulk($accessToken);
            }

        } catch (\Exception $e) {
            Log::error('Bulk sync background SDK exception caught: '.$e->getMessage());
            throw $e;
        }
    }

    private function syncWithGoogleSdkBulk(string $token): void
    {
        $client = new GoogleClient;
        $client->setAccessToken($token);
        $driveService = new GoogleDriveService($client);

        // 1.MEMORY MAP optimization: Fetch all locally stored file checksums in a single query
        $existingFilesMap = DB::table('cloud_files')
            ->where('integration_id', $this->integration->id)
            //value, key
            ->pluck('checksum', 'provider_file_id') // Creates an associative array: [ 'file_id' => 'md5_hash' ]
            ->toArray();

        $pageToken = null;
        $totalProcessed = 0;
        $totalSkipped = 0;

        $filesChunk = [];
        $parentsChunk = [];

        do {
            $optParams = [
                'pageSize' => 100,
                // Explicitly requesting 'md5Checksum' from Google's file resource metadata parameters
                'fields' => 'nextPageToken, files(id, name, mimeType, size, webViewLink, parents, md5Checksum)',
                'q' => 'trashed = false',
                'pageToken' => $pageToken,
            ];

            $results = $driveService->files->listFiles($optParams);
            $filesList = $results->getFiles();
            $pageToken = $results->getNextPageToken();

            foreach ($filesList as $file) {
                $fileId = $file->getId();
                // Google lists folders or native docs without hashes; fallback safely to file metadata strings
                $incomingChecksum = $file->getMd5Checksum() ?? 'no-hash-'.$file->getMimeType();

                // 2. ⚡ THE CHECKSUM DELTA CHECK: Skip compiling if the hash perfectly matches our database, avoiding Laravels object methods as it would create more overhead
                if (($existingFilesMap[$fileId] ?? null) === $incomingChecksum) {
                    $totalSkipped++;

                    continue; // Instantly skip to the next file without doing anything!
                }

                $isFolder = $file->getMimeType() === 'application/vnd.google-apps.folder';
                $now = now()->toDateTimeString();

                // 3. Accumulate data ONLY for new or physically changed files
                $filesChunk[] = [
                    'user_id' => $this->integration->user_id,
                    'integration_id' => $this->integration->id,
                    'provider_file_id' => $fileId,
                    'name' => $file->getName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'view_link' => $file->getWebViewLink(),
                    'checksum' => $incomingChecksum, // Save the current hash
                    'is_folder' => $isFolder ? 1 : 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $parentIds = $file->getParents() ?? ['root'];
                foreach ($parentIds as $parentId) {
                    $parentsChunk[] = [
                        'user_id' => $this->integration->user_id,
                        'integration_id' => $this->integration->id,
                        'provider_file_id' => $fileId,
                        'provider_parent_id' => $parentId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (count($filesChunk) >= self::CHUNK_SIZE) {
                    $this->flushBulkChunks($filesChunk, $parentsChunk);
                    $totalProcessed += count($filesChunk);
                    $filesChunk = [];
                    $parentsChunk = [];
                }
            }

        } while ($pageToken !== null);

        if (! empty($filesChunk)) {
            $this->flushBulkChunks($filesChunk, $parentsChunk);
            $totalProcessed += count($filesChunk);
        }

        Log::info("Delta SDK sync completed. Saved/Updated: {$totalProcessed} files. Unchanged & Skipped: {$totalSkipped} files.");
    }

    private function flushBulkChunks(array $files, array $parents): void
    {
        DB::transaction(function () use ($files, $parents) {
            DB::table('cloud_files')->upsert(
                $files,
                ['integration_id', 'provider_file_id'],
                // Include checksum in the update field fallback definition ruleset
                ['name', 'mime_type', 'size', 'view_link', 'checksum', 'is_folder', 'updated_at']
            );

            DB::table('cloud_file_parents')->insertOrIgnore($parents);
        });
    }
}
