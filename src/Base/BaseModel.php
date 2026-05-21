<?php

namespace Kindharika\ApiStarter\Base;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\Uuid;
use Exception;

abstract class BaseModel extends Model
{
    use SoftDeletes;

    protected string $keyType = 'string';

    protected bool $keyIsUuid = true;

    protected int $uuidVersion = 4;

    public bool $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'email_verified_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            if ($model->keyIsUuid && empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = $model->generateUuid();
            }
        });
    }

    protected function generateUuid(): string
    {
        return match ($this->uuidVersion) {
            1 => Uuid::uuid1()->toString(),
            4 => Uuid::uuid4()->toString(),
            default => throw new Exception("UUID version [{$this->uuidVersion}] not supported."),
        };
    }
}
