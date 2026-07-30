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

    /*
    |--------------------------------------------------------------------------
    | Modular API (NEW — opt-in structure, commands use module:* namespace)
    |--------------------------------------------------------------------------
    | Existing api:* scaffolds under routes/api-starter* stay unchanged.
    | Modules live in app/Modules/{Name} and auto-load Routes/api.php +
    | Routes/api-protected.php under /{route_prefix}/{module-prefix}/...
    |
    |   php artisan module:make Blog
    |   php artisan module:scaffold Blog Post --auth
    |   php artisan module:list
    |   php artisan module:remove Blog Post
    */
    'modules' => [
        'enabled' => (bool) env('API_STARTER_MODULES', true),
        'path' => app_path('Modules'),
        'namespace' => 'Modules',
        // Migrations always use database/migrations (standard Laravel path).
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Trail (Observer + Auditable trait)
    |--------------------------------------------------------------------------
    |   php artisan api:make-audit
    |   php artisan module:scaffold Blog Post --audit
    |   php artisan api:scaffold Post --audit
    |
    | Drivers: database (audit_logs table) | spatie | null
    | Host (optional): composer require spatie/laravel-activitylog
    */
    'audit' => [
        'enabled' => (bool) env('API_STARTER_AUDIT', false),
        'driver' => env('API_STARTER_AUDIT_DRIVER', 'database'), // database|spatie|null
        'table' => env('API_STARTER_AUDIT_TABLE', 'audit_logs'),
    ],

    /*
    |--------------------------------------------------------------------------
    | RBAC Adapter (opt-in — works WITH other packages)
    |--------------------------------------------------------------------------
    | Does NOT replace Spatie / custom RBAC. When enabled, middleware
    | api-starter.permission / api-starter.role delegate to a driver:
    |
    |   spatie  → hasRole / hasPermissionTo / hasAnyPermission on User
    |   gate    → Laravel Gate abilities (+ optional role→ability map)
    |   custom  → your class implementing RbacCheckerInterface
    |   null    → fail-closed (deny) when RBAC enabled
    |
    | When rbac.enabled=false, permission/role middleware are no-ops
    | (auth middleware still applies if route is protected).
    |
    | Host: composer require spatie/laravel-permission  (optional)
    */
    'rbac' => [
        'enabled' => (bool) env('API_STARTER_RBAC', false),
        'driver' => env('API_STARTER_RBAC_DRIVER', 'spatie'), // spatie|gate|custom|null
        'custom' => [
            'checker' => env('API_STARTER_RBAC_CHECKER'), // FQCN implementing RbacCheckerInterface
        ],
        'gate' => [
            // Map role name → Gate ability, e.g. ['admin' => 'access-admin']
            'role_abilities' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SSO / Social Login (opt-in — any Socialite provider)
    |--------------------------------------------------------------------------
    |   php artisan api:make-sso --providers=google,github
    |
    | Host: composer require laravel/socialite laravel/sanctum
    | Configure config/services.php for each provider.
    | Allowlist below — unknown {provider} → 422.
    */
    'social' => [
        'enabled' => (bool) env('API_STARTER_SOCIAL', false),
        'stateless' => (bool) env('API_STARTER_SOCIAL_STATELESS', true),
        'providers' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('API_STARTER_SOCIAL_PROVIDERS', 'google'))
        ))),
    ],
];
