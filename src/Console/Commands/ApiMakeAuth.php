<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Kindharika\ApiStarter\Console\InteractsWithStubs;
use Kindharika\ApiStarter\Support\UserModelPatcher;

class ApiMakeAuth extends Command
{
    use InteractsWithStubs;

    protected $signature = 'api:make-auth
                            {--force : Overwrite existing auth files}
                            {--skip-user-patch : Do not auto-add HasApiTokens to User model}';

    protected $description = 'Generate API auth endpoints: register, login, forgot/reset password, logout, me (Sanctum)';

    public function handle(): int
    {
        $namespace = $this->rootNamespace();
        $force = (bool) $this->option('force');

        if (! $this->option('skip-user-patch')) {
            $result = UserModelPatcher::ensureHasApiTokens();
            match ($result['status']) {
                'patched' => $this->info($result['message']),
                'ok' => $this->line($result['message']),
                default => $this->warn($result['message']),
            };
        }

        $files = [
            [
                'stub' => 'auth/controller.stub',
                'path' => config('api-starter.paths.controller', app_path('Http/Controllers/Api')) . '/AuthController.php',
            ],
            [
                'stub' => 'auth/service.stub',
                'path' => config('api-starter.paths.service', app_path('Services')) . '/Auth/AuthService.php',
            ],
            [
                'stub' => 'auth/request.register.stub',
                'path' => config('api-starter.paths.request', app_path('Http/Requests')) . '/Auth/RegisterRequest.php',
            ],
            [
                'stub' => 'auth/request.login.stub',
                'path' => config('api-starter.paths.request', app_path('Http/Requests')) . '/Auth/LoginRequest.php',
            ],
            [
                'stub' => 'auth/request.forgot.stub',
                'path' => config('api-starter.paths.request', app_path('Http/Requests')) . '/Auth/ForgotPasswordRequest.php',
            ],
            [
                'stub' => 'auth/request.reset.stub',
                'path' => config('api-starter.paths.request', app_path('Http/Requests')) . '/Auth/ResetPasswordRequest.php',
            ],
            [
                'stub' => 'auth/route.public.stub',
                'path' => config('api-starter.paths.route', base_path('routes/api-starter')) . '/auth.php',
            ],
            [
                'stub' => 'auth/route.protected.stub',
                'path' => config('api-starter.paths.route_protected', base_path('routes/api-starter-protected')) . '/auth.php',
            ],
        ];

        foreach ($files as $file) {
            if (is_file($file['path']) && ! $force) {
                $this->warn("Skip (exists): {$file['path']} — use --force to overwrite");
                continue;
            }

            $this->writeStub($file['stub'], $file['path'], [
                'namespace' => $namespace,
            ]);
            $this->info("Created: {$file['path']}");
        }

        $this->writeAuthOpenApi($force);

        $baseUrl = rtrim((string) config('app.url', 'http://localhost'), '/');
        $prefix = trim((string) config('api-starter.route_prefix', 'api'), '/');
        $docsUi = $baseUrl . '/' . trim((string) config('api-starter.openapi.docs_ui', '/api/docs'), '/');

        $this->newLine();
        $this->info('API auth scaffolding completed.');
        $this->newLine();
        $this->line('Public:');
        $this->line("  POST {$baseUrl}/{$prefix}/auth/register");
        $this->line("  POST {$baseUrl}/{$prefix}/auth/login");
        $this->line("  POST {$baseUrl}/{$prefix}/auth/forgot-password");
        $this->line("  POST {$baseUrl}/{$prefix}/auth/reset-password");
        $this->newLine();
        $this->line('Protected (Bearer token):');
        $this->line("  POST {$baseUrl}/{$prefix}/auth/logout");
        $this->line("  GET  {$baseUrl}/{$prefix}/auth/me");
        $this->newLine();
        $this->line("Swagger: {$docsUi}");
        $this->comment('Authorize → paste TOKEN ONLY (without "Bearer "), e.g. 1|xxxxx');
        $this->newLine();

        if (! class_exists(\Laravel\Sanctum\Sanctum::class)) {
            $this->warn('Missing laravel/sanctum:');
            $this->comment('  composer require laravel/sanctum');
            $this->comment('  php artisan vendor:publish --provider="Laravel\\Sanctum\\SanctumServiceProvider"');
            $this->comment('  php artisan migrate');
        }

        return self::SUCCESS;
    }

    protected function writeAuthOpenApi(bool $force): void
    {
        if (! config('api-starter.openapi.enabled', true)) {
            return;
        }

        $dir = config('api-starter.paths.openapi', base_path('storage/api-docs'));
        $path = $dir . '/auth.openapi.json';

        if (is_file($path) && ! $force) {
            $this->warn("Skip OpenAPI (exists): {$path}");
        } else {
            $this->writeStub('auth/openapi.stub.json', $path, [
                'title' => (string) config('api-starter.openapi.title', 'API Documentation'),
                'version' => (string) config('api-starter.openapi.version', '1.0.0'),
                'serverUrl' => (string) config('api-starter.openapi.server_url', '/api'),
            ]);
            $this->info("Created: {$path}");
        }

        $this->mergeOpenApiIndex($dir, $path);
    }

    protected function mergeOpenApiIndex(string $dir, string $resourcePath): void
    {
        if (! is_file($resourcePath)) {
            return;
        }

        $resource = json_decode((string) file_get_contents($resourcePath), true);
        if (! is_array($resource)) {
            return;
        }

        $indexPath = $dir . '/openapi.json';
        $index = is_file($indexPath)
            ? json_decode((string) file_get_contents($indexPath), true)
            : null;

        if (! is_array($index)) {
            $index = [
                'openapi' => '3.0.3',
                'info' => [
                    'title' => config('api-starter.openapi.title', 'API Documentation'),
                    'version' => config('api-starter.openapi.version', '1.0.0'),
                ],
                'servers' => [
                    ['url' => config('api-starter.openapi.server_url', '/api')],
                ],
                'tags' => [],
                'paths' => [],
                'components' => ['schemas' => [], 'securitySchemes' => []],
            ];
        }

        $index['tags'] = array_values(array_filter(
            $index['tags'] ?? [],
            fn ($tag) => ($tag['name'] ?? null) !== 'Auth'
        ));
        $index['tags'][] = [
            'name' => 'Auth',
            'description' => 'Authentication endpoints (Sanctum)',
        ];

        foreach ($resource['paths'] ?? [] as $pathKey => $pathValue) {
            $index['paths'][$pathKey] = $pathValue;
        }

        foreach ($resource['components']['securitySchemes'] ?? [] as $key => $scheme) {
            $index['components']['securitySchemes'][$key] = $scheme;
        }

        $index['servers'] = [
            ['url' => (string) config('api-starter.openapi.server_url', '/api')],
        ];

        file_put_contents(
            $indexPath,
            json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );
    }
}
