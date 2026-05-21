<?php

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;

class ApiMakeModel extends GeneratorCommand
{
    protected $signature = 'api:make-model {name} {--table= : The table name}';
    protected $description = 'Create a new API model extending BaseModel';
    protected $type = 'Model';

    protected function getStub(): string
    {
        return $this->resolveStubPath('model.stub');
    }

    protected function resolveStubPath(string $stub): string
    {
        $customPath = base_path('stubs/api-starter/' . $stub);
        return file_exists($customPath) ? $customPath : __DIR__ . '/../../../stubs/' . $stub;
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\Models';
    }

    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        $table = $this->option('table') ?? Str::snake(Str::pluralStudly(class_basename($name)));

        return str_replace('{{table}}', $table, $stub);
    }
}
