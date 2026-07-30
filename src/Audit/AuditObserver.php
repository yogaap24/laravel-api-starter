<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Audit;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent observer — logs created / updated / deleted / restored via AuditManager.
 */
class AuditObserver
{
    public function __construct(
        protected AuditManager $audit,
    ) {}

    public function created(Model $model): void
    {
        $this->audit->logger()->log('created', $model, null, $this->safeAttributes($model));
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        unset($changes['updated_at']);

        if ($changes === []) {
            return;
        }

        $old = [];
        foreach (array_keys($changes) as $key) {
            $old[$key] = $model->getOriginal($key);
        }

        $this->audit->logger()->log('updated', $model, $old, $changes);
    }

    public function deleted(Model $model): void
    {
        $this->audit->logger()->log('deleted', $model, $this->safeAttributes($model), null);
    }

    public function restored(Model $model): void
    {
        $this->audit->logger()->log('restored', $model, null, $this->safeAttributes($model));
    }

    /**
     * @return array<string, string|int|float|bool|null>
     */
    protected function safeAttributes(Model $model): array
    {
        $hidden = array_merge($model->getHidden(), [
            'password',
            'remember_token',
            'two_factor_secret',
            'two_factor_recovery_codes',
        ]);

        /** @var array<string, string|int|float|bool|null> $attrs */
        $attrs = $model->attributesToArray();

        foreach ($hidden as $key) {
            unset($attrs[$key]);
        }

        return $attrs;
    }
}
