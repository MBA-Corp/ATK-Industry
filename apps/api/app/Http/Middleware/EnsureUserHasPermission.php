<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    public function handle(
        Request $request,
        Closure $next,
        string $permission
    ): Response {
        $user = $request->user();

        if (! $user) {
            return new JsonResponse([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $user->hasPermission($permission)) {
            return new JsonResponse([
                'message' => 'Forbidden.',
            ], 403);
        }

        return $next($request);
    }
}