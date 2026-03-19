<?php

declare(strict_types=1);

namespace Modules\Intelligence\Application\Queue\Middleware;

use Closure;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Modules\Intelligence\Application\Services\ActiveAiProviderResolver;
use Modules\Intelligence\Domain\DTO\AiProviderProfile;
use RuntimeException;

final class ProviderConcurrencyMiddleware
{
    public function handle(object $job, Closure $next): void
    {
        if ((string) config('intelligence.provider', 'chatgpt_codex') !== 'chatgpt_codex') {
            $next($job);

            return;
        }

        $account = $this->resolver()->resolveChatGptCodex();
        if ($account === null || ! $account->enabled) {
            $next($job);

            return;
        }

        if ($this->incrementIfAvailable($account) === false) {
            $this->releaseJob($job);

            return;
        }

        try {
            $next($job);
        } finally {
            $this->decrement($account);
        }
    }

    private function incrementIfAvailable(AiProviderProfile $account): bool
    {
        $store = $this->cacheStore();
        $guardLock = $this->guardLock($store, $account);

        try {
            $guardLock->block(3);
            $current = (int) ($store->get($this->slotKey($account)) ?? 0);

            if ($current >= max(1, $account->maxParallelJobs)) {
                return false;
            }

            $store->put($this->slotKey($account), $current + 1, 600);

            return true;
        } finally {
            $guardLock->release();
        }
    }

    private function decrement(AiProviderProfile $account): void
    {
        $store = $this->cacheStore();
        $guardLock = $this->guardLock($store, $account);

        try {
            $guardLock->block(3);
            $current = (int) ($store->get($this->slotKey($account)) ?? 0);

            if ($current <= 1) {
                $store->forget($this->slotKey($account));

                return;
            }

            $store->put($this->slotKey($account), $current - 1, 600);
        } finally {
            $guardLock->release();
        }
    }

    private function releaseJob(object $job): void
    {
        if (! method_exists($job, 'release')) {
            return;
        }

        $job->release((int) config('intelligence.chatgpt_codex.release_delay_seconds', 5));
    }

    private function slotKey(AiProviderProfile $account): string
    {
        return sprintf('llm:provider:%s:slots', $account->slug);
    }

    private function guardKey(AiProviderProfile $account): string
    {
        return sprintf('llm:provider:%s:guard', $account->slug);
    }

    private function cacheStore(): Repository
    {
        /** @var CacheFactory $cache */
        $cache = app(CacheFactory::class);

        /** @var Repository $store */
        $store = $cache->store((string) config('intelligence.chatgpt_codex.concurrency_cache_store', 'redis'));

        return $store;
    }

    private function resolver(): ActiveAiProviderResolver
    {
        /** @var ActiveAiProviderResolver $resolver */
        $resolver = app(ActiveAiProviderResolver::class);

        return $resolver;
    }

    private function guardLock(Repository $store, AiProviderProfile $account): Lock
    {
        $lockStore = $store->getStore();

        if (! $lockStore instanceof LockProvider) {
            throw new RuntimeException('Configured LLM concurrency cache store does not support atomic locks.');
        }

        return $lockStore->lock($this->guardKey($account), 10);
    }
}
