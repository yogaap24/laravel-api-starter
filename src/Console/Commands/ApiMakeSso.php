<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\Command;
use Kindharika\ApiStarter\Console\InteractsWithStubs;
use Kindharika\ApiStarter\Support\SocialConfig;

class ApiMakeSso extends Command
{
    use InteractsWithStubs;

    protected $signature = 'api:make-sso
                            {--force : Overwrite existing SSO files}
                            {--providers=google : Comma-separated Socialite providers}';

    protected $description = 'Generate SSO / social login (Google + any Socialite provider) — additive to api:make-auth';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $providers = array_values(array_unique(array_filter(array_map(
            static fn ($p) => strtolower(trim($p)),
            explode(',', (string) $this->option('providers'))
        ))));

        if ($providers === []) {
            $providers = ['google'];
        }

        $namespace = $this->rootNamespace();

        $files = [
            [
                'stub' => 'sso/controller.stub',
                'path' => config('api-starter.paths.controller', app_path('Http/Controllers/Api')) . '/SocialAuthController.php',
            ],
            [
                'stub' => 'sso/service.stub',
                'path' => config('api-starter.paths.service', app_path('Services')) . '/Auth/SocialAuthService.php',
            ],
            [
                'stub' => 'sso/request.token.stub',
                'path' => config('api-starter.paths.request', app_path('Http/Requests')) . '/Auth/SocialTokenRequest.php',
            ],
            [
                'stub' => 'sso/route.public.stub',
                'path' => config('api-starter.paths.route', base_path('routes/api-starter')) . '/sso.php',
            ],
        ];

        foreach ($files as $file) {
            if (is_file($file['path']) && ! $force) {
                $this->warn("Skip (exists): {$file['path']} — use --force");
                continue;
            }

            $this->writeStub($file['stub'], $file['path'], [
                'namespace' => $namespace,
                'providersList' => implode(', ', $providers),
            ]);
            $this->info("Created: {$file['path']}");
        }

        $this->writeMigration($force);
        $this->writeOpenApi($force, $providers);
        $this->printProviderHints($providers);

        $baseUrl = rtrim((string) config('app.url', 'http://localhost'), '/');
        $prefix = trim((string) config('api-starter.route_prefix', 'api'), '/');

        $this->newLine();
        $this->info('SSO scaffolding completed.');
        $this->line('API-first (recommended for SPA/mobile):');
        $this->line("  POST {$baseUrl}/{$prefix}/auth/sso/{provider}");
        $this->line('  Body: { "access_token": "..." }  // or id_token for Google');
        $this->newLine();
        $this->line('Optional redirect flow:');
        $this->line("  GET  {$baseUrl}/{$prefix}/auth/sso/{provider}/redirect");
        $this->line("  GET  {$baseUrl}/{$prefix}/auth/sso/{provider}/callback");
        $this->newLine();
        $this->comment('Providers allowlist: ' . implode(', ', $providers));
        $this->comment('Enable: API_STARTER_SOCIAL=true + configure services.{provider} in config/services.php');
        $this->comment('Existing api:make-auth endpoints remain untouched.');

        if (! class_exists(\Laravel\Socialite\Facades\Socialite::class)) {
            $this->newLine();
            $this->warn('Missing laravel/socialite:');
            $this->comment('  composer require laravel/socialite');
        }

        if (! class_exists(\Laravel\Sanctum\Sanctum::class)) {
            $this->warn('Missing laravel/sanctum (needed to issue API tokens after SSO):');
            $this->comment('  composer require laravel/sanctum');
        }

        return self::SUCCESS;
    }

    protected function writeMigration(bool $force): void
    {
        $dir = config('api-starter.paths.migration', database_path('migrations'));
        $existing = glob($dir . '/*_create_social_accounts_table.php') ?: [];

        if ($existing !== [] && ! $force) {
            $this->warn("Skip migration (exists): {$existing[0]}");

            return;
        }

        if ($existing !== [] && $force) {
            foreach ($existing as $file) {
                unlink($file);
            }
        }

        $filename = date('Y_m_d_His') . '_create_social_accounts_table.php';
        $this->writeStub('sso/migration.stub', $dir . '/' . $filename, []);
        $this->info("Created: {$dir}/{$filename}");
    }

    /**
     * @param  list<string>  $providers
     */
    protected function writeOpenApi(bool $force, array $providers): void
    {
        if (! config('api-starter.openapi.enabled', true)) {
            return;
        }

        $dir = config('api-starter.paths.openapi', base_path('storage/api-docs'));
        $path = $dir . '/sso.openapi.json';

        if (is_file($path) && ! $force) {
            $this->warn("Skip OpenAPI (exists): {$path}");
        } else {
            $this->writeStub('sso/openapi.stub.json', $path, [
                'title' => (string) config('api-starter.openapi.title', 'API Documentation'),
                'version' => (string) config('api-starter.openapi.version', '1.0.0'),
                'serverUrl' => (string) config('api-starter.openapi.server_url', '/api'),
                'providersEnum' => json_encode($providers),
            ]);
            $this->info("Created: {$path}");
        }

        $this->mergeOpenApi($dir, $path);
    }

    protected function mergeOpenApi(string $dir, string $resourcePath): void
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
                'servers' => [['url' => config('api-starter.openapi.server_url', '/api')]],
                'tags' => [],
                'paths' => [],
                'components' => ['schemas' => [], 'securitySchemes' => []],
            ];
        }

        $index['tags'] = array_values(array_filter(
            $index['tags'] ?? [],
            fn ($tag) => ($tag['name'] ?? null) !== 'SSO'
        ));
        $index['tags'][] = [
            'name' => 'SSO',
            'description' => 'Social / SSO login (Google + Socialite providers)',
        ];

        foreach ($resource['paths'] ?? [] as $pathKey => $pathValue) {
            $index['paths'][$pathKey] = $pathValue;
        }

        file_put_contents(
            $indexPath,
            json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );
    }

    /**
     * @param  list<string>  $providers
     */
    protected function printProviderHints(array $providers): void
    {
        $this->newLine();
        $this->line('config/services.php examples:');

        foreach ($providers as $provider) {
            if ($provider === 'google') {
                $this->comment(<<<'PHP'
  'google' => [
      'client_id' => env('GOOGLE_CLIENT_ID'),
      'client_secret' => env('GOOGLE_CLIENT_SECRET'),
      'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL').'/api/auth/sso/google/callback'),
  ],
PHP);
                continue;
            }

            $env = strtoupper($provider);
            $this->comment("  '{$provider}' => [");
            $this->comment("      'client_id' => env('{$env}_CLIENT_ID'),");
            $this->comment("      'client_secret' => env('{$env}_CLIENT_SECRET'),");
            $this->comment("      'redirect' => env('{$env}_REDIRECT_URI'),");
            $this->comment('  ],');
        }

        // Hint: sync config providers if published
        if (! SocialConfig::enabled()) {
            $this->newLine();
            $this->comment('Set API_STARTER_SOCIAL=true and api-starter.social.providers to match.');
        }
    }
}
