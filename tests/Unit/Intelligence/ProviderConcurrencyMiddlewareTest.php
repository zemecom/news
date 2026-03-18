<?php

declare(strict_types=1);

namespace Tests\Unit\Intelligence;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Intelligence\Application\Queue\Middleware\ProviderConcurrencyMiddleware;
use Modules\Intelligence\Application\Services\ActiveAiProviderResolver;
use Modules\Intelligence\Infrastructure\Persistence\Models\AiProviderAccount;
use Tests\TestCase;

final class ProviderConcurrencyMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_middleware_releases_job_when_provider_slot_is_busy(): void
    {
        config()->set('intelligence.provider', 'chatgpt_codex');
        config()->set('intelligence.chatgpt_codex.concurrency_cache_store', 'array');
        config()->set('intelligence.chatgpt_codex.release_delay_seconds', 7);

        AiProviderAccount::query()->create([
            'slug' => 'chatgpt-default',
            'provider' => AiProviderAccount::PROVIDER_CHATGPT_CODEX,
            'display_name' => 'ChatGPT Codex',
            'is_enabled' => true,
            'codex_home_subpath' => 'chatgpt-default',
            'default_model' => 'gpt-5.4-mini',
            'max_parallel_jobs' => 1,
            'auth_status' => AiProviderAccount::STATUS_AUTHENTICATED,
        ]);

        app(CacheFactory::class)
            ->store('array')
            ->put('llm:provider:chatgpt-default:slots', 1, 600);

        $middleware = new ProviderConcurrencyMiddleware(
            app(CacheFactory::class),
            app(ActiveAiProviderResolver::class),
        );

        $job = new class
        {
            public ?int $released = null;

            public function release(int $delay): void
            {
                $this->released = $delay;
            }
        };

        $called = false;

        $middleware->handle($job, function () use (&$called): void {
            $called = true;
        });

        $this->assertFalse($called);
        $this->assertSame(7, $job->released);
    }
}
