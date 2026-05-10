<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AdminAiProviderAccountUpdateRequest;
use App\Support\Api\ApiResponse;
use App\Support\Api\V1\AiProviderAccountPresenter;
use Illuminate\Http\JsonResponse;
use Modules\Intelligence\Application\Services\CancelAiProviderLoginAction;
use Modules\Intelligence\Application\Services\LogoutAiProviderAction;
use Modules\Intelligence\Application\Services\RefreshAiProviderStatusAction;
use Modules\Intelligence\Application\Services\StartAiProviderLoginAction;
use Modules\Intelligence\Infrastructure\Persistence\Models\AiProviderAccount;

final class AdminAiProviderAccountController extends Controller
{
    public function __construct(
        private readonly RefreshAiProviderStatusAction $refreshStatus,
        private readonly StartAiProviderLoginAction $startLogin,
        private readonly CancelAiProviderLoginAction $cancelLogin,
        private readonly LogoutAiProviderAction $logout,
    ) {}

    public function index(): JsonResponse
    {
        /** @var list<AiProviderAccount> $accounts */
        $accounts = AiProviderAccount::query()->orderBy('id')->get()->all();

        return ApiResponse::data(array_map(
            static fn (AiProviderAccount $account): array => AiProviderAccountPresenter::present($account),
            $accounts,
        ));
    }

    public function show(AiProviderAccount $aiProviderAccount): JsonResponse
    {
        return ApiResponse::data(AiProviderAccountPresenter::present($aiProviderAccount));
    }

    public function update(AdminAiProviderAccountUpdateRequest $request, AiProviderAccount $aiProviderAccount): JsonResponse
    {
        $aiProviderAccount->fill($request->validated());
        $aiProviderAccount->save();

        return ApiResponse::data(AiProviderAccountPresenter::present($aiProviderAccount->refresh()));
    }

    public function sync(AiProviderAccount $aiProviderAccount): JsonResponse
    {
        $this->refreshStatus->run($aiProviderAccount->toProfile());

        return ApiResponse::data([
            'status' => 'synced',
        ]);
    }

    public function login(AiProviderAccount $aiProviderAccount): JsonResponse
    {
        $this->startLogin->run($aiProviderAccount->toProfile());

        return ApiResponse::data([
            'status' => 'login_started',
        ]);
    }

    public function cancelLogin(AiProviderAccount $aiProviderAccount): JsonResponse
    {
        $this->cancelLogin->run($aiProviderAccount->toProfile());

        return ApiResponse::data([
            'status' => 'login_canceled',
        ]);
    }

    public function logout(AiProviderAccount $aiProviderAccount): JsonResponse
    {
        $this->logout->run($aiProviderAccount->toProfile());

        return ApiResponse::data([
            'status' => 'logged_out',
        ]);
    }
}
