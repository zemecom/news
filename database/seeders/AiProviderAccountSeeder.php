<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Intelligence\Infrastructure\Persistence\Models\AiProviderAccount;

final class AiProviderAccountSeeder extends Seeder
{
    public function run(): void
    {
        AiProviderAccount::query()->updateOrCreate(
            ['slug' => 'chatgpt-default'],
            [
                'provider' => AiProviderAccount::PROVIDER_CHATGPT_CODEX,
                'display_name' => 'ChatGPT Codex',
                'is_enabled' => true,
                'codex_home_subpath' => 'chatgpt-default',
                'default_model' => (string) config('intelligence.chatgpt_codex.model', 'gpt-5.4-mini'),
                'default_reasoning_effort' => config('intelligence.chatgpt_codex.reasoning_effort') ?: null,
                'max_parallel_jobs' => 1,
                'auth_status' => AiProviderAccount::STATUS_NOT_AUTHENTICATED,
                'auth_mode' => 'chatgpt',
                'meta' => [
                    'supports_multi_auth_pool' => false,
                ],
            ],
        );
    }
}
