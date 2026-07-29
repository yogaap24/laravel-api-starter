<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kindharika\ApiStarter\Rbac\RbacManager;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function __construct(
        protected RbacManager $rbac,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
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
