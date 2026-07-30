# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.1.1] - 2026-07-30

### Added
- PHPDoc / type guides on base classes (`BaseApiController`, `BaseService`, `BaseServiceInterface`, `BaseModel`, `ResponseService`, `BaseAuthenticatable`)
- PHPDoc on generated stubs: controllers, services, FormRequests, Resources, models, auth, SSO, module variants
- PHPDoc on RBAC, middleware, DatatableMacro, ModulePaths, SocialConfig

### Changed
- Stub scaffolds document envelope shapes, UUID `$id`, validated() array shapes, and return types for IDE/static analysis

## [2.1.0] - 2026-07-29

### Added
- **Modular API** via separate `module:*` commands (does not replace `api:*`)
  - `module:make`, `module:scaffold`, `module:remove`, `module:list`
  - Modules under `app/Modules/{Name}` with auto-loaded routes + migrations
- **RBAC adapter** (opt-in): drivers `spatie` | `gate` | `custom` | `null`
  - Middleware aliases `api-starter.permission` / `api-starter.role`
  - Compatible with `spatie/laravel-permission` or custom checker
  - `--permission=` / `--role=` on `module:scaffold`
- **SSO / Social login** via `api:make-sso` (Google + any Socialite provider)
  - API-first token exchange + optional redirect/callback
  - Provider allowlist (`API_STARTER_SOCIAL_PROVIDERS`)
  - `social_accounts` migration stub

### Changed
- Config gains `modules`, `rbac`, `social` sections (defaults keep 2.0 behavior)
- Composer `suggest`: `laravel/socialite`, `spatie/laravel-permission`

### Backward compatibility
- All existing `api:scaffold`, `api:make-*`, `api:remove`, `api:make-auth` unchanged
- Existing `routes/api-starter*` loading unchanged
- New features are additive / opt-in

## [2.0.0] - 2026-07-14

### Added
- Laravel **11 / 12 / 13** support
- `api:scaffold` now generates **routes** + **OpenAPI 3** (Swagger-ready JSON)
- Auto-load of scaffolded routes from `routes/api-starter/*.php`
- UUID **v7** support (default) via `ramsey/uuid` ^4.7
- Datatable search operator auto-detect (`ilike` / `like`) + LIKE wildcard escaping
- Packagist auto-update GitHub Action (`.github/workflows/packagist.yml`)
- Branch alias `2.x-dev`

### Changed
- PHP requirement raised to **^8.3** (not forced to 8.5)
- Controllers generate under `app/Http/Controllers/Api`
- Default UUID version: **7** (set `API_STARTER_UUID_VERSION=4` for previous behavior)
- `ResponseService` no longer mutates global `http_response_code()`
- Request stubs fixed: class name is `Store{Model}Request` / `Update{Model}Request`
- Resource scaffold always appends `Resource` suffix
- Scaffold stubs include working `name` / `description` CRUD fields

### Removed
- Firebase Cloud Messaging (FCM) channel, notification, trait, and config
- `ExceptionHelper` (unused Guzzle-coupled helper)
- Non-API path config keys (`notification`, `channel`, `trait`, `seeder`)

### Migration from 1.x
1. Remove any usage of `FCMChannel`, `GeneralNotification`, `FirebaseNotification`
2. Drop `FCM_*` / `api-starter.fcm` env/config
3. Optionally set `API_STARTER_UUID_VERSION=4` if you must keep UUID v4 keys
4. Publish config again: `php artisan vendor:publish --tag=api-starter-config --force`
5. Re-scaffold or move controllers if you relied on `Http/Controllers` (non-`Api`) path

## [1.0.0] - 2025-01-01

### Added
- Initial release: BaseModel, BaseService, BaseApiController, datatable macro, scaffold commands, FCM helpers

[2.1.1]: https://github.com/yogaap24/laravel-api-starter/compare/2.1.0...2.1.1
[2.1.0]: https://github.com/yogaap24/laravel-api-starter/compare/2.0.7...2.1.0
[2.0.0]: https://github.com/yogaap24/laravel-api-starter/compare/1.0.0...2.0.0
[1.0.0]: https://github.com/yogaap24/laravel-api-starter/releases/tag/1.0.0
