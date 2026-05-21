<?php

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ApiMakeService extends Command
{
    protected $signature = 'api:make-service {name} {--model= : The model class}';
    protected $description = 'Create a new API service and interface extending BaseService';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $model = $this->option('model') ?? Str::beforeLast($name, 'Service');
        $interfaceName = $name . 'Interface';

        $this->createService($name, $model);
        $this->createInterface($interfaceName, $model);

        $this->info("API Service [{$name}] created successfully.");
        return self::SUCCESS;
    }

    protected function createService(string $name, string $model): void
    {
        $stub = $this->getStub('service.stub');
        $namespace = config('api-starter.namespace', 'App');
        $modelClass = Str::studly($model);

        $content = str_replace('{{namespace}}', $namespace, $stub);
        $content = str_replace('{{modelClass}}', $modelClass, $content);
        $content = str_replace('{{class}}', $name, $content);

        $path = app_path('Services/' . $modelClass . '/' . $name . '.php');
        $this->ensureDirectoryExists(dirname($path));
        file_put_contents($path, $content);
    }

    protected function createInterface(string $name, string $model): void
    {
        $stub = $this->getStub('service-interface.stub');
        $namespace = config('api-starter.namespace', 'App');
        $modelClass = Str::studly($model);

        $content = str_replace('{{namespace}}', $namespace, $stub);
        $content = str_replace('{{modelClass}}', $modelClass, $content);
        $content = str_replace('{{class}}', $name, $content);

        $path = app_path('Services/' . $modelClass . '/' . $name . '.php');
        $this->ensureDirectoryExists(dirname($path));
        file_put_contents($path, $content);
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
