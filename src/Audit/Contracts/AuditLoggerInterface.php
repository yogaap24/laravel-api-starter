<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Audit\Contracts;

use Illuminate\Database\Eloquent\Model;

interface AuditLoggerInterface
{
    /**
     * @param  array<string, string|int|float|bool|null|array<int|string, string|int|float|bool|null>>|null  $old
     * @param  array<string, string|int|float|bool|null|array<int|string, string|int|float|bool|null>>|null  $new
     */
    public function log(
        string $event,
        Model $model,
        ?array $old = null,
        ?array $new = null,
    ): void;
}
