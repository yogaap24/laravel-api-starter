<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Base;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Builds the standard API JSON envelope used by BaseApiController.
 *
 * @phpstan-type ResponseMeta array{
 *     current_page: int,
 *     from: int|null,
 *     last_page: int,
 *     next_page_url: string|null,
 *     path: string,
 *     per_page: int,
 *     prev_page_url: string|null,
 *     to: int|null,
 *     total: int
 * }
 * @phpstan-type ResponseEnvelope array{
 *     code: int,
 *     success: bool,
 *     message: string|null,
 *     data: array|object|string|int|float|bool|null,
 *     meta?: ResponseMeta
 * }
 */
class ResponseService
{
    /** @var array<string|int, string|int|float|bool|null|array|object>|object|string|int|float|bool|null */
    private array|object|string|int|float|bool|null $data;

    private ?string $message = null;

    private bool $success = false;

    private int $code = 200;

    /**
     * @param  array<string|int, string|int|float|bool|null|array|object>|object|string|int|float|bool|null  $data
     */
    public function __construct(array|object|string|int|float|bool|null $data = null)
    {
        $this->data = $data;
    }

    /**
     * @param  string|array<int|string, string>|null  $message
     * @return object{code: int, success: bool, message: string|null, data: array|object|string|int|float|bool|null, meta?: array<string, int|string|null>}
     */
    public function success(string|array|null $message = null, ?int $responseCode = null): object
    {
        $this->setMessage($message === null || $message === '' || $message === [] ? 'success' : $message);
        $this->code = $responseCode ?? 200;
        $this->success = true;

        return (object) $this->responseWrapper();
    }

    /**
     * @param  string|array<int|string, string>|null  $message
     * @return object{code: int, success: bool, message: string|null, data: array|object|string|int|float|bool|null, meta?: array<string, int|string|null>}
     */
    public function error(string|array|null $message = null, ?int $responseCode = null): object
    {
        $this->setMessage($message === null || $message === '' || $message === [] ? 'error' : $message);
        $this->code = $responseCode ?? 400;
        $this->success = false;

        return (object) $this->responseWrapper();
    }

    /**
     * @return ResponseEnvelope
     */
    private function responseWrapper(): array
    {
        $response = [
            'code' => $this->code,
            'success' => $this->success,
            'message' => $this->message,
        ];

        if ($this->data instanceof LengthAwarePaginator) {
            $paginated = $this->data->toArray();
            $response['data'] = $paginated['data'];

            if (config('api-starter.response.include_meta', true)) {
                $response['meta'] = [
                    'current_page' => $paginated['current_page'],
                    'from' => $paginated['from'],
                    'last_page' => $paginated['last_page'],
                    'next_page_url' => $paginated['next_page_url'],
                    'path' => $paginated['path'],
                    'per_page' => $paginated['per_page'],
                    'prev_page_url' => $paginated['prev_page_url'],
                    'to' => $paginated['to'],
                    'total' => $paginated['total'],
                ];
            }
        } else {
            $response['data'] = $this->data;
        }

        return $response;
    }

    /**
     * @param  string|array<int|string, string>  $message
     */
    private function setMessage(string|array $message): void
    {
        if (is_array($message)) {
            $extract = array_values($message);
            $this->message = isset($extract[0]) ? (string) $extract[0] : 'success';
        } else {
            $this->message = $message;
        }
    }
}
