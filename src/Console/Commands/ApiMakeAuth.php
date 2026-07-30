<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Kindharika\ApiStarter\Console\InteractsWithStubs;
use Kindharika\ApiStarter\Console\ManagesOpenApiDocument;
use Kindharika\ApiStarter\Support\UserModelPatcher;

class ApiMakeAuth extends Command
{
    use InteractsWithStubs;
    use ManagesOpenApiDocument;

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

        // $force kept for BC with call sites; always merge into single openapi.json
        unset($force);

        $fragment = $this->renderOpenApiStub('auth/openapi.stub.json', [
            'title' => (string) config('api-starter.openapi.title', 'API Documentation'),
            'version' => (string) config('api-starter.openapi.version', '1.0.0'),
            'serverUrl' => (string) config('api-starter.openapi.server_url', '/api'),
        ]);

        if ($fragment === null) {
            $this->error('Failed to render auth OpenAPI stub.');

            return;
        }

        $this->mergeFragmentIntoOpenApi(
            fragment: $fragment,
            tagNamesToReplace: ['Auth'],
            pathBasesToReplace: ['auth'],
            newTagName: 'Auth',
            newTagDescription: 'Authentication endpoints (Sanctum)',
        );

        $this->info('OpenAPI updated: ' . $this->openApiIndexPath());
    }
}
