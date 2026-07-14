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

Point Swagger UI / Scalar / Redoc at `storage/api-docs/openapi.json`. Controllers also include `@OA\*` annotations for `darkaonline/l5-swagger` if you add that package in the host app.

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

## Configuration

```php
return [
    'namespace' => 'App',
    'uuid_version' => 7, // 1 | 4 | 7 — use 4 for 1.x behavior
    'route_prefix' => 'api',
    'route_middleware' => ['api'],
    'datatable' => [
        'per_page' => 15,
        'search_operator' => 'auto', // auto | like | ilike
    ],
    'openapi' => [
        'enabled' => true,
        'title' => 'API Documentation',
        'version' => '1.0.0',
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

## Packagist auto-update

Packagist shows *"This package is not auto-updated"* until a GitHub hook (or CI) notifies it.

**Option A — GitHub webhook (recommended)**

1. Packagist → log in with GitHub → grant webhook permissions
2. Or manual webhook on this repo:
   - Payload URL: `https://packagist.org/api/github?username=YOUR_PACKAGIST_USERNAME`
   - Content type: `application/json`
   - Secret: Packagist API token
   - Events: `push` only

**Option B — GitHub Actions** (shipped as `.github/workflows/packagist.yml`)

Add repository secrets:

- `PACKAGIST_USERNAME`
- `PACKAGIST_TOKEN` (Packagist profile → API token)

On every push/tag, CI calls Packagist `update-package`.

Also add a **git tag** for each release (`2.0.0`, …) so Composer can resolve versions.

## Upgrade from 1.x

Breaking: FCM removed. UUID default is now **7**.

1. Remove FCM usage / `FCM_*` env
2. `API_STARTER_UUID_VERSION=4` if you need old UUID v4 keys
3. `composer require kindharika/laravel-api-starter:^2.0`
4. Re-publish config

## Security notes

- Scaffold FormRequests validate `name` / `description`; tighten `authorize()` with policies for real apps
- Prefer `$fillable` on models (stubs do); avoid mass-assigning secrets
- Datatable search escapes `%` / `_` wildcards; still whitelist `search_columns` in production

## License

MIT
