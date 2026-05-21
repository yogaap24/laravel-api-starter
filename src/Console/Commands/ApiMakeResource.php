<?php

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\GeneratorCommand;

class ApiMakeResource extends GeneratorCommand
{
    protected $signature = 'api:make-resource {name}';
    protected $description = 'Create a new API resource class';
    protected $type = 'Resource';

    protected function getStub(): string
    {
        return $this->resolveStubPath('resource.stub');
    }

    protected function resolveStubPath(string $stub): string
    {
        $customPath = base_path('stubs/api-starter/' . $stub);
        return file_exists($customPath) ? $customPath : __DIR__ . '/../../../stubs/' . $stub;
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\Http\Resources';
    }
}
