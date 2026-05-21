<?php

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ApiMakeController extends Command
{
    protected $signature = 'api:make-controller {name} {--model= : The model class}';
    protected $description = 'Create a new API controller extending BaseApiController';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $model = $this->option('model') ?? Str::beforeLast($name, 'Controller');
        $modelClass = Str::studly($model);
        $serviceClass = $modelClass . 'Service';

        $stub = $this->getStub('controller.api.stub');

        $namespace = config('api-starter.namespace', 'App');

        $content = str_replace('{{namespace}}', $namespace, $stub);
        $content = str_replace('{{class}}', $name, $content);
        $content = str_replace('{{modelClass}}', $modelClass, $content);
        $content = str_replace('{{serviceClass}}', $serviceClass, $content);

        $path = app_path('Http/Controllers/' . $name . '.php');
        $this->ensureDirectoryExists(dirname($path));
        file_put_contents($path, $content);

        $this->info("API Controller [{$name}] created successfully.");
        return self::SUCCESS;
    }

    protected function getStub(string $file): string
    {
        $customPath = base_path('stubs/api-starter/' . $file);
        return file_exists($customPath) ? file_get_contents($customPath) : file_get_contents(__DIR__ . '/../../../stubs/' . $file);
    }

    protected function ensureDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
