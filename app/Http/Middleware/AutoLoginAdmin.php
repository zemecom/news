<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class AutoLoginAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->isLocal() && env('ADMIN_AUTO_LOGIN', false) && ! Auth::check()) {
            $user = User::query()->first();
            if ($user) {
                Auth::login($user);
            }
        }

        return $next($request);
    }
}
