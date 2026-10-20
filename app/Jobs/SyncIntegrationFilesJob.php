<?php declare(strict_types=1);

namespace App\Jobs;

use App\Models\Integration;
use App\Services\TokenRefreshService;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDriveService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Generator;

class SyncIntegrationFilesJob implements ShouldQueue
{
    use Queueable;

    protected Integration $integration;
    public int $timeout = 900;
    public int $tries = 3;

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        $this->onQueue('sync');
    }

    public function handle(TokenRefreshService $tokenService): void
    {
        try {
            $accessToken = $tokenService->getValidToken($this->integration);
            $provider = $this->integration->provider->value;

            Log::info("Starting high-throughput generator sync loop for integration ID: {$this->integration->id}");

            if ($provider === 'google') {
                $this->syncGoogleDrive($accessToken);
            }

        } catch (\Exception $e) {
            Log::error("Bulk generator sync background SDK exception caught: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Factory method to isolate the instantiation of the Google SDK Client wrapper.
     */
    protected function getGoogleDriveService(string $token): GoogleDriveService
    {
        $client = new GoogleClient();
        $client->setAccessToken($token);
        return new GoogleDriveService($client);
    }

    /**
     * Lazily streams pages of files out of the Google SDK
     * 
     * @return Generator<int, array>
     */
    private function fetchGoogleFilesGenerator(GoogleDriveService $driveService): Generator
    {
        $pageToken = null;

        do {
            $optParams = [
                'pageSize'  => 100, // Safe page slice size to protect container memory
                'fields'    => 'nextPageToken, files(id, name, mimeType, size, webViewLink, parents, md5Checksum)',
                'q'         => "trashed = false",
                'pageToken' => $pageToken,
            ];

            $results = $driveService->files->listFiles($optParams);
            $pageToken = $results->getNextPageToken(); // Advance internal cursor pointer
            
            // 💡 Yield precisely this page array segment back to our consumer loop
            yield $results->getFiles();

        } while ($pageToken !== null);
    }

    /**
     * Processes the stream coming out of the generator function.
     */
    private function syncGoogleDrive(string $token): void
    {
        $driveService = $this->getGoogleDriveService($token);

        // Fetch our lightweight local checksum hash memory map look-up array
        $existingFilesMap = DB::table('cloud_files')
            ->where('integration_id', $this->integration->id)
            ->pluck('checksum', 'provider_file_id')
            ->toArray();

        $totalProcessed = 0;
        $totalSkipped = 0;
        
        $filesChunk = [];
        $parentsChunk = [];
        $chunkSize = 500;

        // 🔄 Consume the lazy streaming generator pages
        foreach ($this->fetchGoogleFilesGenerator($driveService) as $filesPage) {
            foreach ($filesPage as $file) {
                $fileId = $file->getId();
                $incomingChecksum = $file->getMd5Checksum() ?? 'no-hash-' . $file->getMimeType();

                // Delta hash optimization: skip compiling if the file matches perfectly
                if (($existingFilesMap[$fileId] ?? null) === $incomingChecksum) {
                    $totalSkipped++;
                    continue;
                }

                $isFolder = $file->getMimeType() === 'application/vnd.google-apps.folder';
                $now = now()->toDateTimeString();

                // Accumulate metadata rows
                $filesChunk[] = [
                    'user_id'          => $this->integration->user_id,
                    'integration_id'   => $this->integration->id,
                    'provider_file_id' => $fileId,
                    'name'             => $file->getName(),
                    'mime_type'        => $file->getMimeType(),
                    'size'             => $file->getSize(),
                    'view_link'        => $file->getWebViewLink(),
                    'checksum'         => $incomingChecksum,
                    'is_folder'        => $isFolder ? 1 : 0,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];

                // Accumulate parent relationship items
                $parentIds = $file->getParents() ?? ['root'];
                foreach ($parentIds as $parentId) {
                    $parentsChunk[] = [
                        'user_id'            => $this->integration->user_id,
                        'integration_id'     => $this->integration->id,
                        'provider_file_id'   => $fileId,
                        'provider_parent_id' => $parentId,
                        'created_at'         => $now,
                        'updated_at'         => $now,
                    ];
                }

                // Batch flush to the database when chunks hit our limit boundary
                if (count($filesChunk) >= $chunkSize) {
                    $this->flushBulkChunks($filesChunk, $parentsChunk);
                    $totalProcessed += count($filesChunk);
                    $filesChunk = [];
                    $parentsChunk = [];
                }
            }
        }

        // Flush any leftover remnants lingering inside the arrays
        if (!empty($filesChunk)) {
            $this->flushBulkChunks($filesChunk, $parentsChunk);
            $totalProcessed += count($filesChunk);
        }

        Log::info("Generator sync completed. Saved/Updated: {$totalProcessed} files. Bypassed & Skipped: {$totalSkipped} files.");
    }

    private function flushBulkChunks(array $files, array $parents): void
    {
        DB::transaction(function () use ($files, $parents) {
            DB::table('cloud_files')->upsert(
                $files,
                ['integration_id', 'provider_file_id'],
                ['name', 'mime_type', 'size', 'view_link', 'checksum', 'is_folder', 'updated_at']
            );

            DB::table('cloud_file_parents')->insertOrIgnore($parents);
        });
    }
}
