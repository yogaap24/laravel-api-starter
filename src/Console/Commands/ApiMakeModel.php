<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Kindharika\ApiStarter\Console\BuildsColumnReplacements;
use Kindharika\ApiStarter\Console\InteractsWithStubs;
use Kindharika\ApiStarter\Support\ColumnSchema;

class ApiMakeModel extends GeneratorCommand
{
    use BuildsColumnReplacements;
    use InteractsWithStubs;

    protected $signature = 'api:make-model
                            {name : The model class name}
                            {--table= : The table name}
                            {--columns= : Column spec e.g. name:string,price:decimal:10,2}
                            {--audit : Attach Auditable trait}
                            {--force : Overwrite if the model already exists}';

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

        $schema = ColumnSchema::parse((string) ($this->option('columns') ?: ''));
        $audit = (bool) $this->option('audit');
        $cols = $this->columnReplacements($schema, $audit);

        $replacements = array_merge([
            'namespace' => $namespace,
            'class' => $class,
            'table' => $table,
        ], $cols);

        foreach ($replacements as $key => $value) {
            $stub = str_replace('{{' . $key . '}}', $value, $stub);
        }

        return $stub;
    }
}
