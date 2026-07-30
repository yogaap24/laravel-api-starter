<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Base;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Service-layer base. Hold Eloquent model instance; expose auth helper + response helpers.
 *
 * Concrete services typically implement {@see BaseServiceInterface}.
 */
abstract class BaseService
{
    protected Model $model;

    /** Auth guard name, or null for default guard. */
    protected ?string $guard = null;

    /** Mirrors config('app.debug'). */
    protected bool $debug;

    public function __construct(Model $model)
    {
        $this->model = $model;
        $this->debug = (bool) config('app.debug', false);
    }

    public function getUserAuth(): ?Authenticatable
    {
        /** @var Authenticatable|null $user */
        $user = auth($this->guard)->user();

        return $user;
    }

    public function getTableInstance(): Model
    {
        return $this->model;
    }

    /**
     * Build success envelope object (not HTTP response — prefer controller sendSuccess).
     *
     * @param  array<string|int, string|int|float|bool|null|array|object>|object|string|int|float|bool|null  $data
     * @return object{code: int, success: bool, message: string|null, data: array|object|string|int|float|bool|null, meta?: array<string, int|string|null>}
     */
    protected function sendSuccess(
        array|object|string|int|float|bool|null $data = null,
        ?string $message = null,
        ?int $statusCode = null,
    ): object {
        return (new ResponseService($data))->success($message, $statusCode);
    }

    /**
     * @param  array<string|int, string|int|float|bool|null|array|object>|object|string|int|float|bool|null  $data
     * @return object{code: int, success: bool, message: string|null, data: array|object|string|int|float|bool|null, meta?: array<string, int|string|null>}
     */
    protected function sendError(
        array|object|string|int|float|bool|null $data = null,
        ?string $message = null,
        ?int $statusCode = null,
    ): object {
        return (new ResponseService($data))->error($message, $statusCode);
    }

    /**
     * Normalize FormRequest|array payload to associative array.
     *
     * @param  FormRequest|array<string, string|int|float|bool|null|array<int|string, string|int|float|bool|null>>  $data
     * @return array<string, string|int|float|bool|null|array<int|string, string|int|float|bool|null>>
     */
    protected function extractPayload(FormRequest|array $data): array
    {
        if ($data instanceof FormRequest) {
            /** @var array<string, string|int|float|bool|null|array<int|string, string|int|float|bool|null>> $validated */
            $validated = $data->validated();

            return $validated;
        }

        return $data;
    }
}
