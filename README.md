# Laravel API Starter

Pure **API** package for Laravel **11 / 12 / 13**. Service layer, UUID models, datatable macro, one-command CRUD scaffold + OpenAPI (Swagger).

Requires **PHP ^8.3**.

## Features

- UUID primary keys (default **v7**, configurable to 1 / 4)
- JSON response envelope + pagination meta
- Service layer (`BaseService`, `BaseServiceInterface`)
- `BaseApiController` + API Resources
- Eloquent `datatable()` macro (search / sort / filter / date range)
- `php artisan api:scaffold Post` → model, migration, requests, service, controller, resource, **routes**, **OpenAPI**
- Auto-discovery via Composer; routes under `routes/api-starter/` auto-loaded

## Installation

```bash
composer require kindharika/laravel-api-starter
```

Publish config (optional):

```bash
php artisan vendor:publish --tag=api-starter-config
php artisan vendor:publish --tag=api-starter-stubs
```

## Scaffold (ready-to-use CRUD)

```bash
php artisan api:scaffold Post
# or with migration:
php artisan api:scaffold Post --migrate
```

Creates:

| Artifact | Path |
|----------|------|
| Model | `app/Models/Post.php` |
| Controller | `app/Http/Controllers/Api/PostController.php` |
| Requests | `app/Http/Requests/Post/StorePostRequest.php`, `UpdatePostRequest.php` |
| Service | `app/Services/Post/PostService.php` (+ interface) |
| Resource | `app/Http/Resources/PostResource.php` |
| Migration | `database/migrations/*_create_posts_table.php` |
| Route | `routes/api-starter/posts.php` (auto-loaded) |
| OpenAPI | `storage/api-docs/posts.openapi.json` + merged `openapi.json` |

Endpoints immediately available:

```
GET    /api/posts
POST   /api/posts
GET    /api/posts/{id}
PUT    /api/posts/{id}
DELETE /api/posts/{id}
```

Example body:

```json
{ "name": "Hello", "description": "World" }
```

Individual generators:

```bash
php artisan api:make-model Post
php artisan api:make-controller PostController --model=Post
php artisan api:make-service PostService --model=Post
php artisan api:make-request Post
php artisan api:make-migration Post
php artisan api:make-resource Post
php artisan api:make-route Post
php artisan api:make-openapi Post
```

### Swagger UI

After scaffold, package serves:

- UI: `{APP_URL}/api/docs`
- JSON: `{APP_URL}/api/docs/openapi.json`

Example output:

```
CRUD endpoints:
  GET    http://localhost/api/posts
  POST   http://localhost/api/posts
  ...

Swagger / OpenAPI:
  UI:   http://localhost/api/docs
  JSON: http://localhost/api/docs/openapi.json
```

Controllers also include `@OA\*` annotations for `darkaonline/l5-swagger` if you prefer that package.

## Usage

### Model

```php
use Kindharika\ApiStarter\Base\BaseModel;

class Post extends BaseModel
{
    protected $table = 'posts';
    protected $fillable = ['name', 'description'];
}
```

### Controller

```php
use Kindharika\ApiStarter\Base\BaseApiController;

class PostController extends BaseApiController
{
    // sendSuccess() / sendError()
}
```

### Datatable

```php
$posts = Post::datatable($request->all())->paginate(15);
```

## Optional Sanctum auth

### Route folders

| Folder | Auth | Used by |
|--------|------|---------|
| `routes/api-starter/` | **Public** | Default scaffold + existing resources |
| `routes/api-starter-protected/` | **`auth:sanctum`** | `api:scaffold X --auth` only |

Existing files in `routes/api-starter/` stay public. `--auth` writes only into `api-starter-protected/`.

```bash
php artisan api:scaffold Post                 # → routes/api-starter/posts.php (public)
php artisan api:scaffold CobaAuth --auth      # → routes/api-starter-protected/coba-auths.php
```

`API_STARTER_AUTH=true` only makes **new** scaffolds default to the protected folder — does not lock existing public routes.

### Auth endpoints (login / register / forgot)

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
php artisan api:make-auth
```

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/auth/register` | public |
| POST | `/api/auth/login` | public |
| POST | `/api/auth/forgot-password` | public |
| POST | `/api/auth/reset-password` | public |
| POST | `/api/auth/logout` | Bearer |
| GET | `/api/auth/me` | Bearer |

User model gets `HasApiTokens` **automatically** via `api:make-auth` (skip with `--skip-user-patch`).

Swagger **Authorize**: paste token **only** (no `Bearer ` prefix):

```
1|1qlzDsGAKirfga9dnDrvHDq8temeczVyB6d1T9LMe01fb8c8
```

If protected module still 401, regenerate OpenAPI security:

```bash
php artisan api:make-openapi CobaAuth --auth
php artisan optimize:clear
```

### Remove a module

```bash
php artisan api:remove Post
php artisan api:remove CobaAuth --force
php artisan api:remove Post --keep-migration
```

Deletes model, controller, service, requests, resource, routes, OpenAPI. Migration deleted unless `--keep-migration` (rollback manually if already applied).

## Configuration

```php
return [
    'namespace' => 'App',
    'uuid_version' => 7, // 1 | 4 | 7 — use 4 for 1.x behavior
    'route_prefix' => 'api',
    'route_middleware' => ['api'],
    'auth' => [
        'enabled' => false, // new scaffolds default to --auth when true
        'guard' => 'sanctum',
    ],
    'datatable' => [
        'per_page' => 15,
        'search_operator' => 'auto', // auto | like | ilike
    ],
    'openapi' => [
        'enabled' => true,
        'title' => 'API Documentation',
        'version' => '1.0.0',
        'server_url' => '/api',
    ],
];
```

## Versioning

| Package | PHP | Laravel |
|---------|-----|---------|
| 2.x | ^8.3 | 11 / 12 / 13 |
| 1.x | ^8.2 | 11 / 12 |

SemVer. Breaking changes → major bump. See [CHANGELOG.md](CHANGELOG.md).

```bash
composer require kindharika/laravel-api-starter:^2.0
```

## Security notes

- Scaffold FormRequests validate `name` / `description`; tighten `authorize()` with policies for real apps
- Prefer `$fillable` on models (stubs do); avoid mass-assigning secrets
- Datatable search escapes `%` / `_` wildcards; still whitelist `search_columns` in production

## License

MIT
