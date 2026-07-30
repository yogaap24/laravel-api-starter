<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Audit;

/**
 * Opt-in audit trail via Eloquent Observer ({@see AuditObserver}).
 *
 * Usage on model:
 *   use Kindharika\ApiStarter\Audit\Auditable;
 *   class Post extends BaseModel { use Auditable; }
 *
 * Requires api-starter.audit.enabled=true and audit_logs migration (api:make-audit).
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::observe(AuditObserver::class);
    }
}
