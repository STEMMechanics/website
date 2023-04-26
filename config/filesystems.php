<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been set up for each driver as an example of the required values.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL') . "/storage",
            'public' => true,
        ],

        'cdn' => [
            'driver' => 's3',
            'key' => env('AWS_PUBLIC_ACCESS_KEY_ID'),
            'secret' => env('AWS_PUBLIC_SECRET_ACCESS_KEY'),
            'region' => env('AWS_PUBLIC_DEFAULT_REGION'),
            'bucket' => env('AWS_PUBLIC_BUCKET'),
            'url' => env('AWS_PUBLIC_URL'),
            'endpoint' => env('AWS_PUBLIC_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_PUBLIC_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'public' => true,
            'options' => [
                'ACL' => '',
            ]
        ],

        'private' => [
            'driver' => 's3',
            'key' => env('AWS_PRIVATE_ACCESS_KEY_ID'),
            'secret' => env('AWS_PRIVATE_SECRET_ACCESS_KEY'),
            'region' => env('AWS_PRIVATE_DEFAULT_REGION'),
            'bucket' => env('AWS_PRIVATE_BUCKET'),
            'url' => env('AWS_PRIVATE_URL'),
            'endpoint' => env('AWS_PRIVATE_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_PRIVATE_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'public' => false,
            'options' => [
                'ACL' => '',
            ]
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
