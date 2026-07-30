<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Kindharika\ApiStarter\Console\BuildsColumnReplacements;
use Kindharika\ApiStarter\Console\InteractsWithStubs;
use Kindharika\ApiStarter\Support\ColumnSchema;

class ApiMakeMigration extends Command
{
    use BuildsColumnReplacements;
    use InteractsWithStubs;

    protected $signature = 'api:make-migration
                            {name}
                            {--table= : The table name}
                            {--columns= : Column spec e.g. name:string,description:text?}';

    protected $description = 'Create a new API-ready migration with UUID primary key (database/migrations)';

    public function handle(): int
    {
        $model = Str::studly($this->argument('name'));
        $table = $this->option('table') ?? Str::snake(Str::pluralStudly($model));
        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_create_{$table}_table.php";
        $path = config('api-starter.paths.migration', database_path('migrations')) . '/' . $filename;

        $schema = ColumnSchema::parse((string) ($this->option('columns') ?: ''));
        $cols = $this->columnReplacements($schema, false);

        $this->writeStub('migration.stub', $path, array_merge([
            'table' => $table,
        ], $cols));

        $this->info("Migration [{$filename}] created successfully.");

        return self::SUCCESS;
    }
}
