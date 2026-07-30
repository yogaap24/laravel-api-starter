<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Audit;

use Illuminate\Database\Eloquent\Model;
use Kindharika\ApiStarter\Audit\Contracts\AuditLoggerInterface;

/**
 * Adapter for spatie/laravel-activitylog when installed.
 */
final class SpatieAuditLogger implements AuditLoggerInterface
{
    public function log(string $event, Model $model, ?array $old = null, ?array $new = null): void
    {
        if (! function_exists('activity')) {
            return;
        }

        $logger = activity()
            ->performedOn($model)
            ->event($event);

        if ($old !== null || $new !== null) {
            $logger->withProperties([
                'old' => $old,
                'attributes' => $new,
            ]);
        }

        $logger->log($event);
    }
}
