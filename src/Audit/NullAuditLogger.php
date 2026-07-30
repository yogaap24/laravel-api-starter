<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Audit;

use Illuminate\Database\Eloquent\Model;
use Kindharika\ApiStarter\Audit\Contracts\AuditLoggerInterface;

final class NullAuditLogger implements AuditLoggerInterface
{
    public function log(string $event, Model $model, ?array $old = null, ?array $new = null): void
    {
        // Audit disabled / no-op driver
    }
}
