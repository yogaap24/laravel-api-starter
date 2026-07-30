<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Base;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Thin API controller base. Use {@see sendSuccess()} / {@see sendError()} for JSON envelope.
 *
 * Envelope shape:
 * ```
 * {
 *   "code": int,
 *   "success": bool,
 *   "message": string|array|null,
 *   "data": mixed,
 *   "meta"?: array  // when data is LengthAwarePaginator
 * }
 * ```
 */
abstract class BaseApiController extends Controller
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Return a successful JSON API response.
     *
     * @param  mixed  $data  Payload (model, array, Resource resolve, LengthAwarePaginator, …)
     * @param  string|array<int|string, mixed>|null  $message  Human message or first validation error array
     * @param  int|null  $statusCode  HTTP status (default 200)
     */
    protected function sendSuccess(mixed $data = null, string|array|null $message = null, ?int $statusCode = null): JsonResponse
    {
        $result = (new ResponseService($data))->success($message, $statusCode);

        return response()->json($result, $result->code);
    }

    /**
     * Return an error JSON API response.
     *
     * @param  mixed  $data  Optional error payload
     * @param  string|array<int|string, mixed>|null  $message  Error message
     * @param  int|null  $statusCode  HTTP status (default 400)
     */
    protected function sendError(mixed $data = null, string|array|null $message = null, ?int $statusCode = null): JsonResponse
    {
        $result = (new ResponseService($data))->error($message, $statusCode);

        return response()->json($result, $result->code);
    }
}
