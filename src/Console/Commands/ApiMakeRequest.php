<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Kindharika\ApiStarter\Console\InteractsWithStubs;

class ApiMakeRequest extends Command
{
    use InteractsWithStubs;

    protected $signature = 'api:make-request {name}';

    protected $description = 'Create store and update FormRequest classes for an API resource';

    public function handle(): int
    {
        $modelClass = Str::studly($this->argument('name'));
        $dir = config('api-starter.paths.request', app_path('Http/Requests')) . '/' . $modelClass;

        $this->writeStub('request.store.stub', $dir . '/Store' . $modelClass . 'Request.php', [
            'namespace' => $this->rootNamespace(),
            'modelClass' => $modelClass,
            'class' => 'Store' . $modelClass . 'Request',
        ]);

        $this->writeStub('request.update.stub', $dir . '/Update' . $modelClass . 'Request.php', [
            'namespace' => $this->rootNamespace(),
            'modelClass' => $modelClass,
            'class' => 'Update' . $modelClass . 'Request',
        ]);

        $this->info("API Requests [Store{$modelClass}Request, Update{$modelClass}Request] created successfully.");

        return self::SUCCESS;
    }
}
