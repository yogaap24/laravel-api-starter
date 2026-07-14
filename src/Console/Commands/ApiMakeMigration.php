<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Kindharika\ApiStarter\Console\InteractsWithStubs;

class ApiMakeMigration extends Command
{
    use InteractsWithStubs;

    protected $signature = 'api:make-migration {name} {--table= : The table name}';

    protected $description = 'Create a new API-ready migration with UUID primary key';

    public function handle(): int
    {
        $model = Str::studly($this->argument('name'));
        $table = $this->option('table') ?? Str::snake(Str::pluralStudly($model));
        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_create_{$table}_table.php";
        $path = config('api-starter.paths.migration', database_path('migrations')) . '/' . $filename;

        $this->writeStub('migration.stub', $path, [
            'table' => $table,
        ]);

        $this->info("Migration [{$filename}] created successfully.");

        return self::SUCCESS;
    }
}
