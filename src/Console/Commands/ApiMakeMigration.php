<?php

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ApiMakeMigration extends Command
{
    protected $signature = 'api:make-migration {name} {--table= : The table name}';
    protected $description = 'Create a new API-ready migration with UUID primary key';

    public function handle(): int
    {
        $name = Str::snake(Str::pluralStudly($this->argument('name')));
        $table = $this->option('table') ?? $name;
        $className = 'Create' . Str::studly($name) . 'Table';

        $stub = $this->getStub('migration.stub');
        $content = str_replace('{{table}}', $table, $stub);
        $content = str_replace('{{class}}', $className, $content);

        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_create_{$name}_table.php";
        $path = database_path('migrations/' . $filename);

        File::put($path, $content);

        $this->info("Migration [{$filename}] created successfully.");
        return self::SUCCESS;
    }

    protected function getStub(string $file): string
    {
        $customPath = base_path('stubs/api-starter/' . $file);
        return file_exists($customPath) ? file_get_contents($customPath) : file_get_contents(__DIR__ . '/../../../stubs/' . $file);
    }
}
