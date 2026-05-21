<?php

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ApiMakeRequest extends Command
{
    protected $signature = 'api:make-request {name}';
    protected $description = 'Create store and update FormRequest classes for an API resource';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));

        $this->createRequest('Store' . $name . 'Request', 'request.store.stub');
        $this->createRequest('Update' . $name . 'Request', 'request.update.stub');

        $this->info("API Requests [Store{$name}Request, Update{$name}Request] created successfully.");
        return self::SUCCESS;
    }

    protected function createRequest(string $className, string $stubFile): void
    {
        $stub = $this->getStub($stubFile);
        $namespace = config('api-starter.namespace', 'App');
        $modelClass = Str::studly($this->argument('name'));

        $content = str_replace('{{namespace}}', $namespace, $stub);
        $content = str_replace('{{modelClass}}', $modelClass, $content);
        $content = str_replace('{{class}}', $className, $content);

        $dir = app_path('Http/Requests/' . $modelClass);
        $this->ensureDirectoryExists($dir);

        $path = $dir . '/' . $className . '.php';
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
