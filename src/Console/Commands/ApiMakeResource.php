<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Kindharika\ApiStarter\Console\BuildsColumnReplacements;
use Kindharika\ApiStarter\Console\InteractsWithStubs;
use Kindharika\ApiStarter\Support\ColumnSchema;

class ApiMakeResource extends GeneratorCommand
{
    use BuildsColumnReplacements;
    use InteractsWithStubs;

    protected $signature = 'api:make-resource
                            {name : The resource class name}
                            {--columns= : Column spec for resource fields}
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
        $schema = ColumnSchema::parse((string) ($this->option('columns') ?: ''));
        $cols = $this->columnReplacements($schema, false);

        $replacements = array_merge([
            'namespace' => $namespace,
            'class' => $class,
        ], $cols);

        foreach ($replacements as $key => $value) {
            $stub = str_replace('{{' . $key . '}}', $value, $stub);
        }

        return $stub;
    }
}
