<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Audit;

use Kindharika\ApiStarter\Audit\Contracts\AuditLoggerInterface;

final class AuditManager
{
    protected ?AuditLoggerInterface $logger = null;

    public function enabled(): bool
    {
        return (bool) config('api-starter.audit.enabled', false);
    }

    public function driver(): string
    {
        return (string) config('api-starter.audit.driver', 'database');
    }

    public function logger(): AuditLoggerInterface
    {
        if ($this->logger !== null) {
            return $this->logger;
        }

        if (! $this->enabled()) {
            return $this->logger = new NullAuditLogger;
        }

        return $this->logger = match ($this->driver()) {
            'spatie' => new SpatieAuditLogger,
            'database' => new DatabaseAuditLogger,
            'null' => new NullAuditLogger,
            default => new DatabaseAuditLogger,
        };
    }
}
