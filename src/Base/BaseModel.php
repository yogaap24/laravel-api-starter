<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Base;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\Uuid;

abstract class BaseModel extends Model
{
    use SoftDeletes;

    /**
     * Must remain untyped — parent Model::$keyType has no type declaration.
     *
     * @var string
     */
    protected $keyType = 'string';

    protected bool $keyIsUuid = true;

    /**
     * UUID version used when generating primary keys.
     * Falls back to config('api-starter.uuid_version', 7).
     * Supported: 1, 4, 7.
     */
    protected ?int $uuidVersion = null;

    /**
     * Must remain untyped — parent Model::$incrementing has no type declaration.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Prefer $fillable on concrete models. Empty guarded kept for BC with v1 apps
     * that relied on mass-assignment of all columns — override with $fillable in new code.
     *
     * @var list<string>
     */
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
        $version = $this->uuidVersion ?? (int) config('api-starter.uuid_version', 7);

        return match ($version) {
            1 => Uuid::uuid1()->toString(),
            4 => Uuid::uuid4()->toString(),
            7 => Uuid::uuid7()->toString(),
            default => throw new Exception("UUID version [{$version}] not supported. Use 1, 4, or 7."),
        };
    }
}
