<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Application Root Namespace
    |--------------------------------------------------------------------------
    */
    'namespace' => 'App',

    /*
    |--------------------------------------------------------------------------
    | Generation Paths
    |--------------------------------------------------------------------------
    */
    'paths' => [
        'model' => app_path('Models'),
        'service' => app_path('Services'),
        'controller' => app_path('Http/Controllers/Api'),
        'request' => app_path('Http/Requests'),
        'resource' => app_path('Http/Resources'),
        'migration' => database_path('migrations'),
        'route' => base_path('routes/api-starter'),
        'openapi' => base_path('storage/api-docs'),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Route Prefix
    |--------------------------------------------------------------------------
    | Prefixed when scaffold registers apiResource routes.
    */
    'route_prefix' => env('API_STARTER_ROUTE_PREFIX', 'api'),

    /*
    |--------------------------------------------------------------------------
    | Route Middleware
    |--------------------------------------------------------------------------
    */
    'route_middleware' => ['api'],

    /*
    |--------------------------------------------------------------------------
    | Default UUID Version
    |--------------------------------------------------------------------------
    | Supported: 1, 4, 7 (default 7 — time-ordered, index-friendly)
    | Existing apps on v4 keep working; set API_STARTER_UUID_VERSION=4.
    */
    'uuid_version' => (int) env('API_STARTER_UUID_VERSION', 7),

    /*
    |--------------------------------------------------------------------------
    | Response Envelope
    |--------------------------------------------------------------------------
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
        /*
         * Search operator: "ilike" (PostgreSQL) or "like" (MySQL/SQLite).
         * Auto = detect from default DB connection driver.
         */
        'search_operator' => env('API_STARTER_SEARCH_OPERATOR', 'auto'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAPI / Swagger
    |--------------------------------------------------------------------------
    */
    'openapi' => [
        'enabled' => true,
        'title' => env('API_STARTER_OPENAPI_TITLE', 'API Documentation'),
        'version' => env('API_STARTER_OPENAPI_VERSION', '1.0.0'),
        'server_url' => env('APP_URL', 'http://localhost') . '/api',
        /*
         * HTTP paths served by the package (relative to APP_URL).
         * docs_ui  → Swagger UI page
         * docs_json → OpenAPI JSON
         */
        'docs_ui' => env('API_STARTER_DOCS_UI', '/api/docs'),
        'docs_json' => env('API_STARTER_DOCS_JSON', '/api/docs/openapi.json'),
    ],
];
