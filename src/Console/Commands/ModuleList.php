<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\Command;
use Kindharika\ApiStarter\Modules\ModuleManifest;
use Kindharika\ApiStarter\Modules\ModulePaths;

class ModuleList extends Command
{
    protected $signature = 'module:list';

    protected $description = 'List registered API modules';

    public function handle(): int
    {
        $modules = ModulePaths::list();

        if ($modules === []) {
            $this->warn('No modules found. Create one: php artisan module:make Blog');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($modules as $name) {
            $manifest = ModuleManifest::load($name);
            $rows[] = [
                $name,
                $manifest?->enabled ? 'yes' : 'no',
                $manifest?->prefix ?? '-',
                implode(', ', $manifest?->roles ?? []) ?: '-',
                implode(', ', $manifest?->permissions ?? []) ?: '-',
            ];
        }

        $this->table(['Module', 'Enabled', 'Prefix', 'Roles', 'Permissions'], $rows);

        return self::SUCCESS;
    }
}
