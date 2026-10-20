<?php 
declare(strict_types=1);

use App\Models\User;
use App\Models\Integration;
use App\Models\CloudFile;
use App\Enums\ProviderType;
use App\Jobs\SyncIntegrationFilesJob;
use App\Services\TokenRefreshService;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDriveService;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\FileList;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    
    $this->integration = Integration::create([
        'user_id'                 => $this->user->id,
        'provider'                => ProviderType::GOOGLE,
        'account_email'           => 'test-user@gmail.com',
        'account_name'            => 'Test Dev Workspace',
        'access_token'            => 'valid-stub-token',
        'refresh_token'           => 'valid-long-lived-refresh-token',
        'expires_at'              => time() + 3600, 
    ]);

    // Mock the Google Client globally for all tests to isolate network calls
    $clientMock = mock(GoogleClient::class);
    $clientMock->shouldReceive('setAccessToken')->andReturnNull();
    $this->app->instance(GoogleClient::class, $clientMock);
});

test('it processes and bulk inserts items from google drive sdk into database tables', function () {
    $tokenServiceMock = mock(TokenRefreshService::class);
    $tokenServiceMock->shouldReceive('getValidToken')
        ->once()
        ->with($this->integration)
        ->andReturn('valid-stub-token');

    $mockFile1 = new DriveFile();
    $mockFile1->setId('google-file-id-123');
    $mockFile1->setName('Comprehensive Budget Report.xlsx');
    $mockFile1->setMimeType('application/vnd.ms-excel');
    $mockFile1->setSize(45120);
    $mockFile1->setWebViewLink('https://google.com');
    $mockFile1->setMd5Checksum('hash-checksum-signature-aaa');
    $mockFile1->setParents(['root']);

    $mockFolder = new DriveFile();
    $mockFolder->setId('google-folder-id-999');
    $mockFolder->setName('Archive Resources');
    $mockFolder->setMimeType('application/vnd.google-apps.folder');
    $mockFolder->setSize(null);
    $mockFolder->setWebViewLink('https://google.com');
    $mockFolder->setMd5Checksum(null);
    $mockFolder->setParents(['root']);

    $mockFileList = new FileList();
    $mockFileList->setFiles([$mockFile1, $mockFolder]);
    $mockFileList->setNextPageToken(null);

    // Mock the files subsystem layout resource directly
    $filesResourceMock = mock('Google\Service\Drive\Resource\Files');
    $filesResourceMock->shouldReceive('listFiles')->andReturn($mockFileList);

    // Intercept Google SDK's listFiles method request via Pest's custom double mocking layer
    $driveMock = mock(GoogleDriveService::class);
    $driveMock->files = $filesResourceMock; // Assign our nested resource mock property
    $this->app->instance(GoogleDriveService::class, $driveMock);

    // Act
    $job = new SyncIntegrationFilesJob($this->integration);
    $job->handle($tokenServiceMock);

    // Assert
    expect(CloudFile::count())->toBe(2);
    $this->assertDatabaseHas('cloud_files', ['provider_file_id' => 'google-file-id-123']);
});

test('it utilizes memory maps to skip writing unchanged files when checksum matches', function () {
    CloudFile::create([
        'user_id'          => $this->user->id,
        'integration_id'   => $this->integration->id,
        'provider_file_id' => 'cached-file-777',
        'name'             => 'Static Document.pdf',
        'mime_type'        => 'application/pdf',
        'view_link'        => 'https://google.com',
        'checksum'         => 'identical-md5-hash-signature',
        'is_folder'        => false
    ]);

    $tokenServiceMock = mock(TokenRefreshService::class);
    $tokenServiceMock->shouldReceive('getValidToken')->andReturn('valid-stub-token');

    $unchangedSdkFile = new DriveFile();
    $unchangedSdkFile->setId('cached-file-777');
    $unchangedSdkFile->setName('Static Document.pdf');
    $unchangedSdkFile->setMimeType('application/pdf');
    $unchangedSdkFile->setMd5Checksum('identical-md5-hash-signature');
    $unchangedSdkFile->setParents(['root']);

    $mockFileList = new FileList();
    $mockFileList->setFiles([$unchangedSdkFile]);
    $mockFileList->setNextPageToken(null);

    $filesResourceMock = mock('Google\Service\Drive\Resource\Files');
    $filesResourceMock->shouldReceive('listFiles')->andReturn($mockFileList);

    $driveMock = mock(GoogleDriveService::class);
    $driveMock->files = $filesResourceMock;
    $this->app->instance(GoogleDriveService::class, $driveMock);

    // Act
    $job = new SyncIntegrationFilesJob($this->integration);
    $job->handle($tokenServiceMock);

    // Assert
    expect(CloudFile::count())->toBe(1);
});