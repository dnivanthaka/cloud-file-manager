<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Third-Party Integration Configuration Matrix
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'google' => [
            'name' => 'Google Drive',
            'token_url' => 'https://googleapis.com',
            'scopes' => [
                'https://www.googleapis.com/auth/drive',
            ],
            'offline' => true,
        ],
        'dropbox' => [
            'name' => 'Dropbox Cloud',
            'token_url' => 'https://dropboxapi.com',
            'scopes' => ['files.metadata.read', 'files.content.read'],
            'offline' => true,
        ],
    ],
];
