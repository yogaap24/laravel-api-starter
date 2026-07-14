<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Base;

use Illuminate\Database\Eloquent\Model;

abstract class BaseService
{
    protected Model $model;

    protected ?string $guard = null;

    protected bool $debug;

    public function __construct(Model $model)
    {
        $this->model = $model;
        $this->debug = (bool) config('app.debug', false);
    }

    public function getUserAuth(): mixed
    {
        return auth($this->guard)->user();
    }

    public function getTableInstance(): Model
    {
        return $this->model;
    }

    protected function sendSuccess(mixed $data = null, ?string $message = null, ?int $statusCode = null): object
    {
        return (new ResponseService($data))->success($message, $statusCode);
    }

    protected function sendError(mixed $data = null, ?string $message = null, ?int $statusCode = null): object
    {
        return (new ResponseService($data))->error($message, $statusCode);
    }
}
