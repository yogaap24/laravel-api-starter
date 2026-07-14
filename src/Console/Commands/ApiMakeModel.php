<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Kindharika\ApiStarter\Console\InteractsWithStubs;

class ApiMakeModel extends GeneratorCommand
{
    use InteractsWithStubs;

    protected $signature = 'api:make-model {name} {--table= : The table name}';

    protected $description = 'Create a new API model extending BaseModel';

    protected $type = 'Model';

    protected function getStub(): string
    {
        return $this->getStubPath('model.stub');
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\Models';
    }

    protected function buildClass($name): string
    {
        $stub = $this->files->get($this->getStub());
        $class = class_basename($name);
        $namespace = $this->getNamespace($name);
        $table = $this->option('table') ?? Str::snake(Str::pluralStudly($class));

        return str_replace(
            ['{{namespace}}', '{{class}}', '{{table}}'],
            [$namespace, $class, $table],
            $stub
        );
    }
}
