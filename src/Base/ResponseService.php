<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Base;

use Illuminate\Pagination\LengthAwarePaginator;

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

    public function success(string|array|null $message = null, ?int $responseCode = null): object
    {
        $this->setMessage(empty($message) ? 'success' : $message);
        $this->code = $responseCode ?? 200;
        $this->success = true;

        return (object) $this->responseWrapper();
    }

    public function error(string|array|null $message = null, ?int $responseCode = null): object
    {
        $this->setMessage(empty($message) ? 'error' : $message);
        $this->code = $responseCode ?? 400;
        $this->success = false;

        return (object) $this->responseWrapper();
    }

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
