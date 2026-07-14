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
        // Default / existing scaffolds = PUBLIC
        'route' => base_path('routes/api-starter'),
        // --auth scaffolds only
        'route_protected' => base_path('routes/api-starter-protected'),
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
    | Base middleware for all scaffolded routes. Auth is separate (see below).
    */
    'route_middleware' => ['api'],

    /*
    |--------------------------------------------------------------------------
    | Optional Sanctum Auth (opt-in)
    |--------------------------------------------------------------------------
    | Default routes in routes/api-starter/ are PUBLIC.
    | Only routes in routes/api-starter-protected/ use auth:sanctum.
    |
    |   php artisan api:scaffold Post              # public → api-starter/
    |   php artisan api:scaffold Post --auth       # protected → api-starter-protected/
    |   php artisan api:make-auth                  # login/register/forgot/reset
    |
    | Host app: composer require laravel/sanctum
    */
    'auth' => [
        // When true, NEW scaffolds default to --auth (protected folder).
        // Existing files in routes/api-starter/ stay public either way.
        'enabled' => (bool) env('API_STARTER_AUTH', false),
        'guard' => env('API_STARTER_AUTH_GUARD', 'sanctum'),
        'middleware' => null,
        'user_model' => env('API_STARTER_AUTH_USER_MODEL', 'App\\Models\\User'),
        'token_name' => env('API_STARTER_AUTH_TOKEN_NAME', 'api-token'),
    ],

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
        'server_url' => env('API_STARTER_OPENAPI_SERVER', '/api'),
        /*
         * HTTP paths served by the package (relative to APP_URL).
         * docs_ui  → Swagger UI page
         * docs_json → OpenAPI JSON
         * Use relative paths so Swagger always hits the same origin (avoids CORS / wrong port).
         */
        'docs_ui' => env('API_STARTER_DOCS_UI', '/api/docs'),
        'docs_json' => env('API_STARTER_DOCS_JSON', '/api/docs/openapi.json'),
    ],
];
