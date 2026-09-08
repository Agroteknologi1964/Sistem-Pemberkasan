<?php

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'documents_disk' => env('DOCUMENTS_DISK', env('FILESYSTEM_DISK', 'local')),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app'),
            'throw'  => false,
        ],

        // Disk publik (untuk aset yang perlu diakses langsung via URL)
        'public' => [
            'driver'     => 'local',
            'root'       => storage_path('app/public'),
            'url'        => env('APP_URL') . '/storage',
            'visibility' => 'public',
            'throw'      => false,
        ],

        // Disk untuk dokumen PDF (private, tidak bisa diakses langsung)
        'documents' => [
            'driver' => 'local',
            'root'   => storage_path('app/documents'),
            'throw'  => true,
        ],

        // S3-compatible object storage (AWS S3, Cloudflare R2, Wasabi, MinIO, etc.)
        's3' => [
            'driver'                  => 's3',
            'key'                     => env('AWS_ACCESS_KEY_ID'),
            'secret'                  => env('AWS_SECRET_ACCESS_KEY'),
            'region'                  => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'bucket'                  => env('AWS_BUCKET'),
            'url'                     => env('AWS_URL'),
            'endpoint'                => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw'                   => false,
        ],

    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
