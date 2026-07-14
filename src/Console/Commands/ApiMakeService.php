<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Kindharika\ApiStarter\Console\InteractsWithStubs;

class ApiMakeService extends Command
{
    use InteractsWithStubs;

    protected $signature = 'api:make-service {name} {--model= : The model class}';

    protected $description = 'Create a new API service and interface extending BaseService';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        if (! str_ends_with($name, 'Service')) {
            $name .= 'Service';
        }

        $model = $this->option('model') ?? Str::beforeLast($name, 'Service');
        $modelClass = Str::studly($model);
        $interfaceName = $name . 'Interface';

        $dir = config('api-starter.paths.service', app_path('Services')) . '/' . $modelClass;

        $this->writeStub('service.stub', $dir . '/' . $name . '.php', [
            'namespace' => $this->rootNamespace(),
            'modelClass' => $modelClass,
            'class' => $name,
        ]);

        $this->writeStub('service-interface.stub', $dir . '/' . $interfaceName . '.php', [
            'namespace' => $this->rootNamespace(),
            'modelClass' => $modelClass,
            'class' => $interfaceName,
        ]);

        $this->info("API Service [{$name}] created successfully.");

        return self::SUCCESS;
    }
}
