<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Kindharika\ApiStarter\Console\BuildsColumnReplacements;
use Kindharika\ApiStarter\Console\InteractsWithStubs;
use Kindharika\ApiStarter\Support\ColumnSchema;

class ApiMakeRequest extends Command
{
    use BuildsColumnReplacements;
    use InteractsWithStubs;

    protected $signature = 'api:make-request
                            {name}
                            {--columns= : Column spec for validation rules}';

    protected $description = 'Create store and update FormRequest classes for an API resource';

    public function handle(): int
    {
        $modelClass = Str::studly($this->argument('name'));
        $dir = config('api-starter.paths.request', app_path('Http/Requests')) . '/' . $modelClass;
        $schema = ColumnSchema::parse((string) ($this->option('columns') ?: ''));
        $cols = $this->columnReplacements($schema, false);

        $this->writeStub('request.store.stub', $dir . '/Store' . $modelClass . 'Request.php', array_merge([
            'namespace' => $this->rootNamespace(),
            'modelClass' => $modelClass,
            'class' => 'Store' . $modelClass . 'Request',
        ], $cols));

        $this->writeStub('request.update.stub', $dir . '/Update' . $modelClass . 'Request.php', array_merge([
            'namespace' => $this->rootNamespace(),
            'modelClass' => $modelClass,
            'class' => 'Update' . $modelClass . 'Request',
        ], $cols));

        $this->info("API Requests [Store{$modelClass}Request, Update{$modelClass}Request] created successfully.");

        return self::SUCCESS;
    }
}
