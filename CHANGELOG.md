# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.2.8] - 2026-08-01

### Fixed
- **Module Swagger showed a shape the API never returns**: `stubs/module/openapi.stub.json` 200/201 responses now document the real `ResponseService` envelope (`code` / `success` / `message` / `data`, plus `meta` on the paginated list) instead of a bare payload — same shape the flat `stubs/openapi.stub.json` already used. Changes generated OpenAPI for newly scaffolded module resources
- Module list parameters get their `sort_column` / `sort_type` defaults back (`created_at` / `desc`)
- **Route marker drift**: `// api-starter:resource:{Name}:begin/end` was hand-written in four places and the `module:remove` copy had already diverged from the `module:scaffold` one — route cleanup could silently miss blocks. All four now go through one owner
- `InteractsWithStubs::renderStub()` resolved its stub via `getStub()`, whose name collides with the abstract zero-arg `Illuminate\Console\GeneratorCommand::getStub()`. In a `GeneratorCommand` subclass the class override wins and PHP silently drops the extra argument, so `renderStub()` / `writeStub()` received a filesystem path instead of the stub body. Both now resolve through `getStubPath()`. No shipped command hit this — the two affected commands had worked around it — so no generated output changes

### Added
- `Modules\ResourceRouteMarker` — sole owner of the resource route marker format (write / strip / discover); `ModuleScaffold::buildRouteBlock`, `ModuleScaffold::stripResourceRoutes`, `ModuleRemove::removeResource`, and `ApiStarterServiceProvider::discoverProtectedModulePaths` all delegate to it

### Changed
- `module:*` commands register before `api:*` in `php artisan list`; the missing-spec 404 now names `php artisan module:scaffold {Module}` first
- `api:scaffold` / `api:remove` descriptions identify the **flat (non-module)** surface — `api:remove` used to describe itself as removing a "module", colliding with `module:remove`
- `config/api-starter.php`: `modules` block moved above the Sanctum auth block, heading no longer labels it "NEW / opt-in" — **no key, default, or env var renamed** (`API_STARTER_MODULES` and friends unchanged; nothing to republish)
- `ModuleScaffold` stops passing two unused replacements (`route`, `module`) into `stubs/module/controller.stub`
- `stubs/module/resource.stub` docblock interpolates the class name instead of a fixed `Module API Resource.`
- `ApiStarterServiceProvider::registerOpenApiRoutes` is now route registration only — the ~105-line inline JSON closure and the Swagger UI closure moved to `serveOpenApiJson()`, `serveSwaggerUi()`, `protectedFlatRouteSegments()`, and `protectedModulePaths()`. Pure extraction: no served bytes change
- `ApiMakeModel` / `ApiMakeResource` build their stub through `InteractsWithStubs::renderStub()` instead of a hand-rolled `{{token}}` loop

## [2.2.7] - 2026-07-30

### Fixed
- `module:remove` crash: `preg_match(): Unknown modifier '/'` — `$ref` regex delimiter conflict with `#/components/schemas/...`

## [2.2.6] - 2026-07-30

### Fixed
- **OpenAPI leftovers on remove**: `module:remove` / `api:remove` now delete paths + tags + schemas (`Model`/`Store`/`Update`), harvest `$ref` from removed paths, infer schema names from URL segments, then prune orphans
- Module gone from disk? Still cleans OpenAPI (`module:remove Course --force` or `--openapi-only`)

### Added
- `php artisan api:openapi-prune --prefix=course` / `--schema=Course` / `--orphans` for leftover cleanup
- Module OpenAPI stub: GET responses `$ref` model schema (helps future harvest)

## [2.2.5] - 2026-07-30

### Changed
- **Module routes: one file** — `Routes/api.php` only; `--auth` writes `auth:sanctum` middleware inside that file
- `module:make` no longer creates empty `api-protected.php`
- OpenAPI Bearer detection scans `api.php` for auth middleware (+ legacy `api-protected.php`)
- README: when to use auth, why flat still has two folders

### Fixed
- `module:remove` whole module also strips OpenAPI tags/schemas for module models

### Compatibility
- Existing `Routes/api-protected.php` still auto-loads with outer Sanctum middleware

## [2.2.4] - 2026-07-30

### Changed
- **Single Swagger doc only**: all scaffolds merge into `storage/api-docs/openapi.json`
- No more per-module / per-resource `*.openapi.json` fragments (legacy files purged on generate/remove)

## [2.2.3] - 2026-07-30

### Fixed
- Primary module resource (`module:scaffold Course`) uses URL **`/api/course`** — not `/api/course/courses`
- Swagger: single tag `Course` (not `Course/Course`); purge stale `/course/courses` paths + duplicate openapi files

## [2.2.2] - 2026-07-30

### Fixed
- `module:remove Blog` (whole module) now deletes related `database/migrations/*_create_*` files (unless `--keep-migration`)
- Enum/set values accept **`;`** (shell-safe) as well as `|`; docs warn to **quote** `--columns`

### Changed
- README restructured (TOC, shell tip, clearer sections)

## [2.2.1] - 2026-07-30


### Fixed
- `module:scaffold Course` works with **one name** (resource defaults to module name)
- Column type **`enum`** / **`set`** supported (`status:enum:draft|published`; bare `enum` → string(64) + warn)
- Alias `timestamps` / `timestampsTz` → `timestamp` / `timestampTz` (single column; `created_at`/`updated_at` tetap dari stub)

### Added
- Full Laravel Blueprint column types for `--columns`: char, text variants, all integers (signed/unsigned), float/double/unsignedDecimal, dateTime(Tz)/time(Tz)/year, jsonb, binary, ulid, ipAddress, macAddress, foreignUlid, spatial types
- **OpenAPI/Swagger schemas follow `--columns`**: `{{Model}}`, `{{Model}}Store`, `{{Model}}Update` + `@OA\Schema` on controllers; `search_columns` example uses real column names
- `api:make-openapi` / `api:make-controller` accept `--columns=`

## [2.2.0] - 2026-07-30

### Added
- **Typed common ground**: `DatatableFilter` DTO; `BaseServiceInterface` without `mixed`
- **Column-driven scaffold**: `--columns=name:string,price:decimal:10,2,status:boolean?` (+ interactive prompt)
- **Audit trail**: `Auditable` trait + `AuditObserver` + drivers `database` | `spatie` | `null`
  - `php artisan api:make-audit`
  - `--audit` on `api:scaffold` / `module:scaffold`
- `declare(strict_types=1)` on all generation stubs

### Changed
- Module migrations now write to **`database/migrations`** (standard path) — no longer per-module
- Response/service helpers use union types instead of `mixed`
- Removed `modules.load_migrations` config (obsolete)

### Backward compatibility
- Existing generated app code still runs; re-scaffold to get typed services/controllers
- Legacy `app/Modules/*/Database/Migrations` folders ignored going forward

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

[2.2.8]: https://github.com/yogaap24/laravel-api-starter/compare/2.2.7...2.2.8
[2.2.4]: https://github.com/yogaap24/laravel-api-starter/compare/2.2.3...2.2.4
[2.2.3]: https://github.com/yogaap24/laravel-api-starter/compare/2.2.2...2.2.3
[2.2.2]: https://github.com/yogaap24/laravel-api-starter/compare/2.2.1...2.2.2
[2.2.1]: https://github.com/yogaap24/laravel-api-starter/compare/2.2.0...2.2.1
[2.2.0]: https://github.com/yogaap24/laravel-api-starter/compare/2.1.1...2.2.0
[2.1.1]: https://github.com/yogaap24/laravel-api-starter/compare/2.1.0...2.1.1
[2.1.0]: https://github.com/yogaap24/laravel-api-starter/compare/2.0.7...2.1.0
[2.0.0]: https://github.com/yogaap24/laravel-api-starter/compare/1.0.0...2.0.0
[1.0.0]: https://github.com/yogaap24/laravel-api-starter/releases/tag/1.0.0
