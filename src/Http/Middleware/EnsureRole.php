<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kindharika\ApiStarter\Rbac\RbacManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alias: api-starter.role:{role}[,…]
 *
 * Returns JSON 401 if unauthenticated, 403 if missing role.
 * When api-starter.rbac.enabled=false → pass-through.
 */
class EnsureRole
{
    public function __construct(
        protected RbacManager $rbac,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     * @param  string  ...$roles  Role names from middleware parameters
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if ($roles === []) {
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
            return $next($request);
        }

        if (! $this->rbac->hasRole($user, $roles)) {
            return response()->json([
                'code' => 403,
                'success' => false,
                'message' => 'Forbidden. Missing required role.',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
