<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Kindharika\ApiStarter\Audit\Contracts\AuditLoggerInterface;

/**
 * Writes rows to audit_logs (created by api:make-audit migration).
 */
final class DatabaseAuditLogger implements AuditLoggerInterface
{
    public function log(string $event, Model $model, ?array $old = null, ?array $new = null): void
    {
        $table = (string) config('api-starter.audit.table', 'audit_logs');

        if (! Schema::hasTable($table)) {
            return;
        }

        $user = auth()->user();

        DB::table($table)->insert([
            'id' => (string) Str::uuid(),
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => (string) $model->getKey(),
            'event' => $event,
            'old_values' => $old === null ? null : json_encode($old, JSON_THROW_ON_ERROR),
            'new_values' => $new === null ? null : json_encode($new, JSON_THROW_ON_ERROR),
            'user_id' => $user?->getAuthIdentifier(),
            'user_type' => $user !== null ? $user::class : null,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
