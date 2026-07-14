<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Kindharika\ApiStarter\Console\InteractsWithStubs;

class ApiMakeResource extends GeneratorCommand
{
    use InteractsWithStubs;

    protected $signature = 'api:make-resource
                            {name : The resource class name}
                            {--force : Overwrite if the resource already exists}';

    protected $description = 'Create a new API resource class';

    protected $type = 'Resource';

    protected function getStub(): string
    {
        return $this->getStubPath('resource.stub');
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\Http\Resources';
    }

    protected function getNameInput(): string
    {
        $name = parent::getNameInput();

        return str_ends_with($name, 'Resource') ? $name : $name . 'Resource';
    }

    protected function buildClass($name): string
    {
        $stub = $this->files->get($this->getStub());
        $class = class_basename($name);
        $namespace = $this->getNamespace($name);

        return str_replace(
            ['{{namespace}}', '{{class}}'],
            [$namespace, $class],
            $stub
        );
    }
}
