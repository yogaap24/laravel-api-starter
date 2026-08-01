# Laravel API Starter

Pure API package for Laravel **11 / 12 / 13** (PHP **^8.3**).

Scaffold CRUD + OpenAPI/Swagger, service layer, UUID models, datatable macro.
**2.2** adds column-driven generation, modules, RBAC adapter, SSO. Modules (`module:*`) are the **recommended structure** for new APIs; the flat `api:*` surface remains fully supported and unchanged.

---

## Table of contents

1. [Install](#install)
2. [Quick start: Modules (`module:*`)](#quick-start-modules-module)
3. [Columns (`--columns`)](#columns---columns)
4. [Flat API (`api:*`)](#flat-api-api)
5. [Auth / Sanctum](#auth--sanctum)
6. [RBAC](#rbac)
7. [SSO](#sso)
8. [Audit](#audit)
9. [Swagger](#swagger)
10. [Remove](#remove)
11. [Config](#config)
12. [Security](#security)
13. [Versioning](#versioning)

---

## Install

```bash
composer require kindharika/laravel-api-starter
```

Optional publish:

```bash
php artisan vendor:publish --tag=api-starter-config
php artisan vendor:publish --tag=api-starter-stubs
```

---

## Quick start: Modules (`module:*`)

Isolated APIs under `app/Modules/{Name}`. This is the recommended default. The flat `api:scaffold` flow stays fully supported as an alternative — see [Flat API (`api:*`)](#flat-api-api).

```bash
php artisan module:make Blog
php artisan module:scaffold Course
php artisan module:scaffold Blog Post --columns='title:string,body:text?,published_at:timestamp?'
php artisan module:scaffold Blog Post --auth --audit --permission=posts.manage
php artisan module:list
```

One name is enough: `module:scaffold Course` → module **and** model `Course`, URL **`/api/course`** (not `/api/course/courses`).

Nested resources still work: `module:scaffold Blog Post` → `/api/blog/posts`.

```
app/Modules/Blog/
  Models/…  Http/…  Services/…  Routes/api.php
database/migrations/…   # standard path (not inside module)
```

### Module routes = **one file** (`Routes/api.php`)

| When          | How                                                                                  | Result                                              |
| ------------- | ------------------------------------------------------------------------------------ | --------------------------------------------------- |
| Public CRUD   | `module:scaffold Blog Post`                                                          | `Route::apiResource(...)` without auth              |
| Login needed  | `module:scaffold Blog Post --auth` **or** `API_STARTER_AUTH=true`                     | `auth:sanctum` middleware **inside** `api.php`      |
| + permission  | `--permission=posts.manage`                                                          | Adds `api-starter.permission:…` on the same route   |

Example output of `--auth`:

```php
// Routes/api.php
Route::middleware(['auth:sanctum'])->apiResource('posts', \Modules\Blog\Http\Controllers\PostController::class);
```

**Edit one file only.** Mix public + protected inside `api.php` — anything that needs a token goes in `Route::middleware(['auth:sanctum'])->group(...)`.

Legacy `Routes/api-protected.php` (older modules) is still loaded, with auth applied outside the file. New modules do **not** create it. You may move its contents into `api.php` and then delete `api-protected.php`.

```
GET/POST /api/blog/posts
```

| Command           | Purpose                                                        |
| ----------------- | -------------------------------------------------------------- |
| `module:scaffold` | **Recommended default** — CRUD inside a module (`app/Modules`) |
| `api:scaffold`    | Flat alternative — CRUD directly under `app/`                  |

---

## Columns (`--columns`)

Generate fillable, migration, validation, resource, **and Swagger schemas** from one spec. Works with both `module:scaffold` and `api:scaffold`.

### Shell tip (important)

Quote `--columns=…`. Unquoted `|` is a **shell pipe** and breaks the command.

```bash
# ✅ good — quotes + ; for enum (shell-safe)
php artisan module:scaffold Course --columns='name:string,domain_course:enum:online;offline,slug:string,publish_at:timestamp,duration_minutes:int'

# ✅ also OK — quotes + |
php artisan module:scaffold Course --columns='name:string,domain_course:enum:online|offline,slug:string'

# ❌ bad — shell eats |
php artisan module:scaffold Course --columns=name:string,domain_course:enum:online|offline
```

### Spec format

```
name:type
name:type?
name:string:100
name:decimal:10,2
status:enum:a;b;c
tags:set:a;b
user_id:foreignUuid:users
```

- Suffix `?` = nullable
- Enum/set values: prefer **`;`** (or `|` inside quotes)
- Bare `status:enum` → `string(64)` + warning
- `publish_at:timestamps` → **one** `timestamp` column (`created_at`/`updated_at` already in stub)

### Common types

| Group   | Types                                                                                                              |
| ------- | ------------------------------------------------------------------------------------------------------------------ |
| String  | `char`, `string`, `text`, `mediumText`, `longText`                                                                 |
| Int     | `integer`/`int`, `tinyInteger`, `smallInteger`, `mediumInteger`, `bigInteger` + `unsigned*`                        |
| Number  | `float`, `double`, `decimal`, `unsignedDecimal`                                                                    |
| Bool    | `boolean`/`bool`                                                                                                   |
| Date    | `date`, `dateTime`, `dateTimeTz`, `time`, `timeTz`, `timestamp`/`timestamps`, `timestampTz`/`timestampsTz`, `year` |
| Other   | `json`, `jsonb`, `enum`, `set`, `binary`, `uuid`, `ulid`, `ipAddress`/`ip`, `macAddress`/`mac`                     |
| FK      | `foreignId`, `foreignUuid`, `foreignUlid`                                                                          |
| Spatial | `geometry`, `point`, …                                                                                             |

Without `--columns`: interactive prompt, or default `name` + `description`.

---

## Flat API (`api:*`)

Flat (non-module) CRUD generated straight into `app/`. Fully supported — use it for existing apps already on this layout, or when a module is more structure than you need. For new APIs prefer [`module:scaffold`](#quick-start-modules-module).

```bash
php artisan api:scaffold Post
php artisan api:scaffold Post --migrate
```

| Artifact   | Path                                                      |
| ---------- | --------------------------------------------------------- |
| Model      | `app/Models/Post.php`                                     |
| Controller | `app/Http/Controllers/Api/PostController.php`             |
| Requests   | `app/Http/Requests/Post/…`                                |
| Service    | `app/Services/Post/…`                                     |
| Resource   | `app/Http/Resources/PostResource.php`                     |
| Migration  | `database/migrations/*_create_posts_table.php`            |
| Route      | `routes/api-starter/posts.php`                            |
| OpenAPI    | `storage/api-docs/openapi.json` only (single Swagger doc) |

```
GET/POST       /api/posts
GET/PUT/DELETE /api/posts/{id}
```

Single generators: `api:make-model|controller|service|request|migration|resource|route|openapi`.

---

## Auth / Sanctum

### Flat `api:*` (two folders — different loaders)

| Folder                          | Auth           | When used                                                    |
| ------------------------------- | -------------- | ------------------------------------------------------------ |
| `routes/api-starter/`           | Public         | Default `api:scaffold`                                       |
| `routes/api-starter-protected/` | `auth:sanctum` | `--auth` or `API_STARTER_AUTH=true` (new scaffolds only)     |

The flat surface keeps two folders because the loader attaches middleware **outside** the file. Modules already use one file with middleware **inside**.

### Setup Sanctum

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
php artisan api:make-auth
```

| Method | Path                        | Auth   |
| ------ | --------------------------- | ------ |
| POST   | `/api/auth/register`        | public |
| POST   | `/api/auth/login`           | public |
| POST   | `/api/auth/forgot-password` | public |
| POST   | `/api/auth/reset-password`  | public |
| POST   | `/api/auth/logout`          | Bearer |
| GET    | `/api/auth/me`              | Bearer |

Swagger **Authorize**: paste token **only** (no `Bearer ` prefix).

**Security:** a route without `auth:sanctum` can be hit by anyone. Never put sensitive data on a public route.

---

## RBAC

Opt-in. No forced package.

```env
API_STARTER_RBAC=true
API_STARTER_RBAC_DRIVER=spatie   # spatie | gate | custom | null
```

```bash
composer require spatie/laravel-permission   # optional
```

```php
Route::middleware(['api-starter.permission:posts.manage'])->…;
Route::middleware(['api-starter.role:admin'])->…;
```

Custom: `API_STARTER_RBAC_CHECKER` → class implementing `RbacCheckerInterface`.
When `rbac.enabled=false`, permission/role middleware = **no-op**.

---

## SSO

```bash
composer require laravel/socialite laravel/sanctum
php artisan api:make-sso --providers=google
php artisan migrate
```

```env
API_STARTER_SOCIAL=true
API_STARTER_SOCIAL_PROVIDERS=google
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI="${APP_URL}/api/auth/sso/google/callback"
```

| Method | Path                                                             |
| ------ | ---------------------------------------------------------------- |
| POST   | `/api/auth/sso/{provider}` (`access_token` or Google `id_token`) |
| GET    | `/api/auth/sso/{provider}/redirect`                              |
| GET    | `/api/auth/sso/{provider}/callback`                              |

---

## Audit

```bash
php artisan api:make-audit
php artisan migrate
# .env: API_STARTER_AUDIT=true
php artisan api:scaffold Post --audit
```

Drivers: `database` | `spatie` | `null`. Trait: `Kindharika\ApiStarter\Audit\Auditable`.

---

## Swagger

One file: `storage/api-docs/openapi.json`

- UI: `{APP_URL}/api/docs`
- JSON: `{APP_URL}/api/docs/openapi.json`

Scaffold / auth / SSO / module output all merge into that one file — no `*.openapi.json` fragments are written.

Schemas follow `--columns`. Success responses document the full envelope — `code`, `success`, `message`, `data`, plus `meta` on paginated lists — for module and flat resources alike, matching `Kindharika\ApiStarter\Base\ResponseService`.

Generated controllers carry no `@OA` annotations. `openapi.json` is the single source of truth and is built purely by merging JSON fragments — the package never parses annotations. Host apps that want `darkaonline/l5-swagger` to scan their controllers can publish the stubs (`php artisan vendor:publish --tag=api-starter-stubs`) and add annotations there; the `{{openApiResourceProperties}}`, `{{openApiRequestProperties}}` and `{{openApiStoreRequired}}` replacements are still supplied to custom stubs.

```bash
php artisan api:make-openapi Post --columns='title:string,body:text?'
php artisan api:make-openapi CobaAuth --auth

# Leftover schema/properties after a remove?
php artisan api:openapi-prune --prefix=course --force
php artisan api:openapi-prune --schema=Course --force
php artisan api:openapi-prune --orphans --force

# Module already gone from disk — clean OpenAPI only:
php artisan module:remove Course --openapi-only --force
```

Hard refresh Swagger UI after a prune (browser cache).

---

## Remove

`api:remove` removes a **flat** scaffolded resource — files under `app/`, routes in `routes/api-starter*`, and its OpenAPI entries. It is not `module:remove` and never touches `app/Modules`.
`module:remove` removes a module resource, or an entire module.

```bash
# Flat resource (api:scaffold output)
php artisan api:remove Post
php artisan api:remove Post --keep-migration

# Module resource
php artisan module:remove Blog Post

# Whole module (also deletes related database/migrations + OpenAPI paths/tags/schemas)
php artisan module:remove Blog --force
php artisan module:remove Blog --force --keep-migration
php artisan module:remove Blog --openapi-only --force   # OpenAPI only
```

Deletes model, controller, service, requests, resource, routes, OpenAPI (paths + tags + schemas).
Migrations deleted unless `--keep-migration`. If already migrated → `migrate:rollback` manually.

---

## Config

Publish `config/api-starter.php`. Highlights:

| Key                  | Notes                                     |
| -------------------- | ----------------------------------------- |
| `uuid_version`       | `7` (default), `4`, or `1`                |
| `modules.path`       | `app/Modules`                             |
| `auth.enabled`       | New scaffolds default to protected routes |
| `rbac.driver`        | `spatie` \| `gate` \| `custom` \| `null`  |
| `openapi.enabled`    | Swagger UI + JSON                         |
| `datatable.per_page` | Default page size                         |

Typed filter DTO: `Kindharika\ApiStarter\Support\DatatableFilter::fromRequest($request)`.

---

## Security

- FormRequests validate scaffold fields; add policies for real apps
- Prefer `$fillable`; never mass-assign secrets
- Datatable escapes `%`/`_`; whitelist `search_columns` in production
- SSO: provider allowlist; Google `id_token` audience checked
- RBAC fail-closed when driver broken/unknown

---

## Versioning

| Package | PHP  | Laravel      |
| ------- | ---- | ------------ |
| 2.2.x   | ^8.3 | 11 / 12 / 13 |
| 2.1.x   | ^8.3 | 11 / 12 / 13 |
| 1.x     | ^8.2 | 11 / 12      |

```bash
composer require kindharika/laravel-api-starter:^2.2
```

See [CHANGELOG.md](CHANGELOG.md).

## License

MIT
