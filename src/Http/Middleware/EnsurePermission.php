<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kindharika\ApiStarter\Rbac\RbacManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alias: api-starter.permission:{permission}[,…]
 *
 * Returns JSON 401 if unauthenticated, 403 if missing permission.
 * When api-starter.rbac.enabled=false → pass-through (auth still from route group).
 */
class EnsurePermission
{
    public function __construct(
        protected RbacManager $rbac,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     * @param  string  ...$permissions  Permission names from middleware parameters
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        if ($permissions === []) {
            return $next($request);
        }

        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'code' => 401,
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], 401);
        }

        if (! $this->rbac->enabled()) {
            // RBAC off → permission middleware is a no-op (auth still applies via route group).
            return $next($request);
        }

        if (! $this->rbac->hasPermission($user, $permissions)) {
            return response()->json([
                'code' => 403,
                'success' => false,
                'message' => 'Forbidden. Missing required permission.',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
