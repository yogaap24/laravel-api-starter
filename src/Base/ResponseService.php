<?php

namespace Kindharika\ApiStarter\Base;

use Illuminate\Pagination\LengthAwarePaginator;

class ResponseService
{
    private mixed $data;
    private string|array|null $message = null;
    private bool $success = false;

    public function __construct(mixed $data = null)
    {
        $this->data = $data;
    }

    public function success(string|array|null $message = null, ?int $responseCode = null): object
    {
        $message = empty($message) ? 'success' : $message;

        $this->setMessage($message);
        $this->setResponseCode($responseCode);
        $this->success = true;

        return (object) $this->responseWrapper();
    }

    public function error(string|array|null $message = null, ?int $responseCode = null): object
    {
        $message = empty($message) ? 'error' : $message;

        $this->setMessage($message);
        $this->setResponseCode($responseCode);
        $this->success = false;

        return (object) $this->responseWrapper();
    }

    private function responseWrapper(): array
    {
        $response = [
            'code'    => http_response_code(),
            'success' => $this->success,
            'message' => $this->message,
        ];

        if ($this->data instanceof LengthAwarePaginator) {
            $paginated = $this->data->toArray();
            $response['data'] = $paginated['data'];
            $response['meta'] = [
                'current_page'   => $paginated['current_page'],
                'from'           => $paginated['from'],
                'last_page'      => $paginated['last_page'],
                'next_page_url'  => $paginated['next_page_url'],
                'path'           => $paginated['path'],
                'per_page'       => $paginated['per_page'],
                'prev_page_url'  => $paginated['prev_page_url'],
                'to'             => $paginated['to'],
                'total'          => $paginated['total'],
            ];
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

    private function setResponseCode(?int $responseCode): void
    {
        if (!empty($responseCode) && is_numeric($responseCode)) {
            http_response_code($responseCode);
        }
    }
}
