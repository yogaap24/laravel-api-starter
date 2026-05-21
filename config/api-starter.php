<?php

return [
    'namespace' => 'App',
    'paths' => [
        'model' => app_path('Models'),
        'service' => app_path('Services'),
        'controller' => app_path('Http/Controllers'),
        'request' => app_path('Http/Requests'),
        'resource' => app_path('Http/Resources'),
        'migration' => database_path('migrations'),
        'notification' => app_path('Notifications'),
        'channel' => app_path('Channels'),
        'trait' => app_path('Traits'),
        'seeder' => database_path('seeders'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default UUID Version
    |--------------------------------------------------------------------------
    | Supported: 1, 4
    */
    'uuid_version' => env('API_STARTER_UUID_VERSION', 4),

    /*
    |--------------------------------------------------------------------------
    | FCM Configuration
    |--------------------------------------------------------------------------
    */
    'fcm' => [
        'enabled' => env('FCM_ENABLED', true),
        'server_key' => env('FCM_SERVER_KEY', null),
        'endpoint' => env('FCM_ENDPOINT', 'https://fcm.googleapis.com/fcm/send'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Envelope
    |--------------------------------------------------------------------------
    | Configure default response structure.
    */
    'response' => [
        'include_meta' => true,
        'wrap_data' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Datatable Macro Defaults
    |--------------------------------------------------------------------------
    */
    'datatable' => [
        'per_page' => 15,
        'default_sort_column' => 'created_at',
        'default_sort_direction' => 'desc',
    ],
];
