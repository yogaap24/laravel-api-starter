<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Kindharika\ApiStarter\Console\InteractsWithStubs;
use Kindharika\ApiStarter\Modules\ModuleManifest;
use Kindharika\ApiStarter\Modules\ModulePaths;

class ModuleMake extends Command
{
    use InteractsWithStubs;

    protected $signature = 'module:make
                            {name : Module name (e.g. Blog)}
                            {--prefix= : Route prefix override (default: kebab module name)}
                            {--force : Overwrite module.json if exists}';

    protected $description = 'Create a new API module skeleton (separate from api:* scaffolds)';

    public function handle(): int
    {
        if (! config('api-starter.modules.enabled', true)) {
            $this->error('Modules disabled in config (api-starter.modules.enabled).');

            return self::FAILURE;
        }

        $name = Str::studly($this->argument('name'));
        $dir = ModulePaths::module($name);

        if (ModulePaths::exists($name) && ! $this->option('force')) {
            $this->warn("Module [{$name}] already exists. Use --force to refresh module.json.");

            return self::SUCCESS;
        }

        $prefix = $this->option('prefix')
            ? Str::kebab((string) $this->option('prefix'))
            : Str::kebab($name);

        $dirs = [
            $dir . '/Models',
            $dir . '/Http/Controllers',
            $dir . '/Http/Requests',
            $dir . '/Http/Resources',
            $dir . '/Services',
            $dir . '/Routes',
        ];

        foreach ($dirs as $path) {
            $this->ensureDirectoryExists($path);
        }

        $manifest = new ModuleManifest(
            name: $name,
            enabled: true,
            prefix: $prefix,
        );
        $manifest->write($name);

        // Single routes file — auth via middleware inside the file (see module:scaffold --auth)
        $routes = $dir . '/Routes/api.php';
        if (! is_file($routes) || $this->option('force')) {
            file_put_contents($routes, <<<'PHP'
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
| Module routes (ONE file).
| Public:  Route::apiResource(...)
| Auth:    Route::middleware(['auth:sanctum'])->group(function () { ... });
| Legacy api-protected.php still loads if present (BC) — prefer this file.
*/

PHP);
        }

        $ns = ModulePaths::namespace($name);
        $routePrefix = trim((string) config('api-starter.route_prefix', 'api'), '/') . '/' . $prefix;

        $this->info("Module [{$name}] created at {$dir}");
        $this->line("Namespace: {$ns}");
        $this->line("Route prefix: /{$routePrefix}");
        $this->line('Routes file: Routes/api.php (single file)');
        $this->newLine();
        $this->comment("Next: php artisan module:scaffold {$name} --columns='title:string,body:text?'");
        $this->comment('Auth: add --auth (writes middleware in api.php). Migrations → database/migrations.');

        return self::SUCCESS;
    }
}
