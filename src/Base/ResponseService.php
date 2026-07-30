<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Base;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Builds the standard API JSON envelope used by BaseApiController.
 *
 * Success/error return a plain object with public properties:
 * - code (int)
 * - success (bool)
 * - message (string|array|null)
 * - data (mixed)
 * - meta (array) — only when $data is LengthAwarePaginator and include_meta=true
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
 *     message: string|array|null,
 *     data: mixed,
 *     meta?: ResponseMeta
 * }
 */
class ResponseService
{
    private mixed $data;

    private string|array|null $message = null;

    private bool $success = false;

    private int $code = 200;

    public function __construct(mixed $data = null)
    {
        $this->data = $data;
    }

    /**
     * @param  string|array<int|string, mixed>|null  $message
     * @param  int|null  $responseCode  HTTP status code (default 200)
     * @return object{code: int, success: bool, message: string|array|null, data: mixed, meta?: array<string, mixed>}
     */
    public function success(string|array|null $message = null, ?int $responseCode = null): object
    {
        $this->setMessage(empty($message) ? 'success' : $message);
        $this->code = $responseCode ?? 200;
        $this->success = true;

        return (object) $this->responseWrapper();
    }

    /**
     * @param  string|array<int|string, mixed>|null  $message
     * @param  int|null  $responseCode  HTTP status code (default 400)
     * @return object{code: int, success: bool, message: string|array|null, data: mixed, meta?: array<string, mixed>}
     */
    public function error(string|array|null $message = null, ?int $responseCode = null): object
    {
        $this->setMessage(empty($message) ? 'error' : $message);
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
     * @param  string|array<int|string, mixed>|null  $message
     */
    private function setMessage(string|array|null $message): void
    {
        if (is_array($message)) {
            $extract = array_values($message);
            $this->message = $extract[0] ?? 'success';
        } else {
            $this->message = $message;
        }
    }
}
