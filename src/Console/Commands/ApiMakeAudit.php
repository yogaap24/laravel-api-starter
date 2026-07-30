<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\Command;
use Kindharika\ApiStarter\Console\InteractsWithStubs;

class ApiMakeAudit extends Command
{
    use InteractsWithStubs;

    protected $signature = 'api:make-audit
                            {--force : Overwrite existing audit migration}';

    protected $description = 'Publish audit_logs migration for observer-based audit trail';

    public function handle(): int
    {
        $dir = config('api-starter.paths.migration', database_path('migrations'));
        $this->ensureDirectoryExists($dir);

        $existing = glob($dir . '/*_create_audit_logs_table.php') ?: [];
        if ($existing !== [] && ! $this->option('force')) {
            $this->warn("Skip (exists): {$existing[0]}");
        } else {
            if ($existing !== [] && $this->option('force')) {
                foreach ($existing as $file) {
                    unlink($file);
                }
            }
            $filename = date('Y_m_d_His') . '_create_audit_logs_table.php';
            $this->writeStub('audit/migration.stub', $dir . '/' . $filename, [
                'table' => (string) config('api-starter.audit.table', 'audit_logs'),
            ]);
            $this->info("Created: {$dir}/{$filename}");
        }

        $this->newLine();
        $this->info('Audit trail ready (Observer + Auditable trait).');
        $this->line('1. Set API_STARTER_AUDIT=true');
        $this->line('2. php artisan migrate');
        $this->line('3. use Auditable on models, or scaffold with --audit');
        $this->comment('Drivers: database (default) | spatie | null');

        if ($this->option('force') === false && ! config('api-starter.audit.enabled', false)) {
            $this->warn('Remember: API_STARTER_AUDIT is currently false.');
        }

        return self::SUCCESS;
    }
}
