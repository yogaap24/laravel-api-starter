# Laravel API Starter

A Laravel package that brings the best of the `laravel-template` API architecture into a reusable, modern, and maintainable form. Supports **Laravel 11.x, 12.x, and 13.x**.

## Features

- UUID-based models out of the box (v4 by default, configurable)
- Unified JSON response envelope with pagination metadata
- Service layer pattern (BaseService, BaseServiceInterface)
- Base API Controller (BaseApiController)
- Datatable macro (search, sort, filter, date-range on Eloquent Builder)
- Firebase Cloud Messaging (FCM) infrastructure (Channel, Notification, Trait)
- Artisan scaffold generators (model, service, controller, request, migration, resource)
- Auto-discovery via Composer extras

## Installation

```bash
composer require kindharika/laravel-api-starter
```

Or add via path repository for local development:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "./package/laravel-api-starter"
    }
  ]
}
```

## Publish Config

```bash
php artisan vendor:publish --tag=api-starter-config
```

## Usage

### Base classes

Your models should extend `BaseModel`:

```php
use Kindharika\ApiStarter\Base\BaseModel;

class Post extends BaseModel
{
    protected $table = 'posts';
}
```

Your controllers should extend `BaseApiController`:

```php
use Kindharika\ApiStarter\Base\BaseApiController;

class PostController extends BaseApiController
{
    // use sendSuccess() / sendError()
}
```

Your services should extend `BaseService` and implement `BaseServiceInterface`:

```php
use Kindharika\ApiStarter\Base\BaseService;
use Kindharika\ApiStarter\Base\BaseServiceInterface;

class PostService extends BaseService implements BaseServiceInterface
{
    // implements dataTable, getById, create, update, delete
}
```

### Datatable macro

Once installed, the macro is available on any query builder:

```php
$posts = Post::datatable($request->all())->paginate(15);
```

### Scaffolding

Generate a full resource scaffold with one command:

```bash
php artisan api:scaffold Post
```

This generates:
- `app/Models/Post.php`
- `app/Http/Controllers/PostController.php`
- `app/Http/Requests/Post/StorePost.php`
- `app/Http/Requests/Post/UpdatePost.php`
- `app/Services/Post/PostService.php`
- `app/Services/Post/PostServiceInterface.php`
- `app/Http/Resources/PostResource.php`
- Database migration

Or generate individually:

```bash
php artisan api:make-model Post
php artisan api:make-controller PostController --model=Post
php artisan api:make-service PostService --model=Post
php artisan api:make-request Post
php artisan api:make-migration Post
php artisan api:make-resource Post
```

### Firebase Notification

Use the trait in your service or notification class:

```php
use Kindharika\ApiStarter\Traits\FirebaseNotification;

class YourService
{
    use FirebaseNotification;

    public function notify($user)
    {
        $this->sendNotification($user, 'Title", "Body", "INFO");
    }
}
```

## Configuration

`config/api-starter.php`:

```php
return [
    'namespace' => 'App',
    'uuid_version' => 4,
    'fcm' => [
        'enabled' => true,
        'server_key' => env('FCM_SERVER_KEY'),
        'endpoint' => 'https://fcm.googleapis.com/fcm/send',
    ],
    'datatable' => [
        'per_page' => 15,
        'default_sort_column' => 'created_at',
        'default_sort_direction' => 'desc',
    ],
];
```

## Upgrading from Template

If you were previously using the `laravel-template` as-is, migrate by:
1. `composer require kindharika/laravel-api-starter`
2. Replace `AppModel` with `Kindharika\ApiStarter\Base\BaseModel`
3. Replace `AppAuthenticatable` with `Kindharika\ApiStarter\Base\BaseAuthenticatable`
4. Replace `AppService` with `Kindharika\ApiStarter\Base\BaseService`
5. Replace `ApiController` with `Kindharika\ApiStarter\Base\BaseApiController`
6. Replace `ResponseService` with `Kindharika\ApiStarter\Base\ResponseService`
7. Remove `DatatableServiceProvider` — the macro is registered automatically now
8. Use `api:scaffold` to generate new resources instead of manual stubs

## License

MIT
