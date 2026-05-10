<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Support\Api\ApiResponse;
use App\Support\Api\V1\AuthenticatedUserPresenter;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $credentials = [
            'email' => (string) ($validated['email'] ?? ''),
            'password' => (string) ($validated['password'] ?? ''),
        ];

        if (! Auth::attempt($credentials, remember: false)) {
            return ApiResponse::error('invalid_credentials', 'Invalid credentials.', 422);
        }

        $request->session()->regenerate();

        $user = $request->user();

        if ($user === null) {
            return ApiResponse::error('unauthenticated', 'Unauthenticated.', 401);
        }

        return ApiResponse::data(AuthenticatedUserPresenter::present($user));
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return ApiResponse::error('unauthenticated', 'Unauthenticated.', 401);
        }

        return ApiResponse::data(AuthenticatedUserPresenter::present($user));
    }

    public function logout(Request $request): JsonResponse
    {
        $guard = Auth::guard('web');

        if ($guard instanceof StatefulGuard) {
            $guard->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::data([
            'logged_out' => true,
        ]);
    }
}
