<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Api\ApiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserIsAdmin
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            if ($request->is('api/v1/*')) {
                return ApiResponse::error('unauthenticated', 'Unauthenticated.', 401);
            }

            return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        }

        if (! $user instanceof User || ! $user->isAdmin()) {
            if ($request->is('api/v1/*')) {
                return ApiResponse::error('forbidden', 'Forbidden.', 403);
            }

            return new JsonResponse(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}
