<?php

namespace Kindharika\ApiStarter\Services;

use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;

class ExceptionHelper
{
    public static function httpException(Exception $exception): void
    {
        if ($exception instanceof ClientException || $exception instanceof ServerException) {
            $response = json_decode($exception->getResponse()->getBody(), true);
            $message = $response['message'] ?? $exception->getMessage();
            throw new Exception($message, 422, $exception);
        }

        throw $exception;
    }
}
