<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Kindharika\ApiStarter\Console\InteractsWithStubs;

class ApiMakeController extends Command
{
    use InteractsWithStubs;

    protected $signature = 'api:make-controller {name} {--model= : The model class}';

    protected $description = 'Create a new API controller extending BaseApiController';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        if (! str_ends_with($name, 'Controller')) {
            $name .= 'Controller';
        }

        $model = $this->option('model') ?? Str::beforeLast($name, 'Controller');
        $modelClass = Str::studly($model);
        $route = Str::kebab(Str::pluralStudly($modelClass));

        $path = config('api-starter.paths.controller', app_path('Http/Controllers/Api')) . '/' . $name . '.php';

        $this->writeStub('controller.api.stub', $path, [
            'namespace' => $this->rootNamespace(),
            'class' => $name,
            'modelClass' => $modelClass,
            'route' => $route,
        ]);

        $this->info("API Controller [{$name}] created successfully.");

        return self::SUCCESS;
    }
}
