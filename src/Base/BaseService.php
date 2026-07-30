<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Base;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

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

    /**
     * Currently authenticated user for the configured guard.
     *
     * @return Authenticatable|null
     */
    public function getUserAuth(): mixed
    {
        return auth($this->guard)->user();
    }

    /**
     * Underlying Eloquent model instance injected into this service.
     */
    public function getTableInstance(): Model
    {
        return $this->model;
    }

    /**
     * Build success envelope object (not HTTP response — prefer controller sendSuccess).
     *
     * @param  mixed  $data
     * @param  string|null  $message
     * @param  int|null  $statusCode
     * @return object{code: int, success: bool, message: string|array|null, data: mixed, meta?: array<string, mixed>}
     */
    protected function sendSuccess(mixed $data = null, ?string $message = null, ?int $statusCode = null): object
    {
        return (new ResponseService($data))->success($message, $statusCode);
    }

    /**
     * Build error envelope object (not HTTP response — prefer controller sendError).
     *
     * @param  mixed  $data
     * @param  string|null  $message
     * @param  int|null  $statusCode
     * @return object{code: int, success: bool, message: string|array|null, data: mixed, meta?: array<string, mixed>}
     */
    protected function sendError(mixed $data = null, ?string $message = null, ?int $statusCode = null): object
    {
        return (new ResponseService($data))->error($message, $statusCode);
    }
}
