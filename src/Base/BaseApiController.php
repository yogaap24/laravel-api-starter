<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Base;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

abstract class BaseApiController extends Controller
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    protected function sendSuccess(mixed $data = null, string|array|null $message = null, ?int $statusCode = null): JsonResponse
    {
        $result = (new ResponseService($data))->success($message, $statusCode);

        return response()->json($result, $result->code);
    }

    protected function sendError(mixed $data = null, string|array|null $message = null, ?int $statusCode = null): JsonResponse
    {
        $result = (new ResponseService($data))->error($message, $statusCode);

        return response()->json($result, $result->code);
    }
}
