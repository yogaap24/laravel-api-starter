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
 *   "message": string|null,
 *   "data": array|object|string|int|float|bool|null,
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
     * @param  array<string|int, string|int|float|bool|null|array|object>|object|string|int|float|bool|null  $data
     * @param  string|array<int|string, string>|null  $message
     */
    protected function sendSuccess(
        array|object|string|int|float|bool|null $data = null,
        string|array|null $message = null,
        ?int $statusCode = null,
    ): JsonResponse {
        $result = (new ResponseService($data))->success($message, $statusCode);

        return response()->json($result, $result->code);
    }

    /**
     * @param  array<string|int, string|int|float|bool|null|array|object>|object|string|int|float|bool|null  $data
     * @param  string|array<int|string, string>|null  $message
     */
    protected function sendError(
        array|object|string|int|float|bool|null $data = null,
        string|array|null $message = null,
        ?int $statusCode = null,
    ): JsonResponse {
        $result = (new ResponseService($data))->error($message, $statusCode);

        return response()->json($result, $result->code);
    }
}
