@php
    $summaries = is_array($summaries ?? null) ? $summaries : [];
@endphp

<div class="flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
    <div>
        <p class="text-[0.7rem] font-semibold uppercase tracking-[0.24em] text-gray-500 dark:text-gray-400">
            Queue Overview
        </p>
        <h3 class="mt-2 text-xl font-semibold tracking-tight text-gray-950 dark:text-white">
            Queues & Analysis Health
        </h3>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
            Live snapshot of RabbitMQ queues used by crawler, intelligence and media workers.
        </p>
    </div>

    <p class="text-xs text-gray-500 dark:text-gray-400">
        Polling Laravel workers may keep `consumer_count = 0`, so this block is a broker snapshot, not a process supervisor.
    </p>
</div>

@if ($summaries !== [])
    <div class="mt-6 grid gap-4 xl:grid-cols-3">
        @foreach ($summaries as $summary)
            @php
                $isUnavailable = ($summary['status'] ?? 'ok') !== 'ok';
                $hasConsumers = ((int) ($summary['consumer_count'] ?? 0)) > 0;
                $accentClasses = $isUnavailable
                    ? 'border-rose-200 bg-rose-50 dark:border-rose-400/20 dark:bg-rose-400/10'
                    : ($hasConsumers
                        ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-400/20 dark:bg-emerald-400/10'
                        : 'border-sky-200 bg-sky-50 dark:border-sky-400/20 dark:bg-sky-400/10');
                $stateLabel = $isUnavailable ? 'Unavailable' : ($hasConsumers ? 'Consumer active' : 'Broker OK');
                $badgeClasses = $isUnavailable
                    ? 'bg-rose-600 text-white dark:bg-rose-500'
                    : ($hasConsumers
                        ? 'bg-emerald-600 text-white dark:bg-emerald-500'
                        : 'bg-sky-600 text-white dark:bg-sky-500');
            @endphp

            <div class="rounded-2xl border px-5 py-5 {{ $accentClasses }}">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-gray-500 dark:text-gray-400">
                            Queue
                        </p>
                        <h4 class="mt-2 text-base font-semibold text-gray-950 dark:text-white">{{ $summary['queue'] }}</h4>
                    </div>

                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $badgeClasses }}">
                        {{ $stateLabel }}
                    </span>
                </div>

                <dl class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl bg-white/80 px-4 py-3 dark:bg-gray-950/30">
                        <dt class="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400">Messages</dt>
                        <dd class="mt-2 text-lg font-semibold text-gray-950 dark:text-white">{{ $summary['message_count'] ?? 'n/a' }}</dd>
                    </div>
                    <div class="rounded-2xl bg-white/80 px-4 py-3 dark:bg-gray-950/30">
                        <dt class="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400">Consumers</dt>
                        <dd class="mt-2 text-lg font-semibold text-gray-950 dark:text-white">{{ $summary['consumer_count'] ?? 'n/a' }}</dd>
                    </div>
                    <div class="rounded-2xl bg-white/80 px-4 py-3 dark:bg-gray-950/30">
                        <dt class="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400">Failed Jobs</dt>
                        <dd class="mt-2 text-lg font-semibold text-gray-950 dark:text-white">{{ $summary['failed_count'] }}</dd>
                    </div>
                    <div class="rounded-2xl bg-white/80 px-4 py-3 dark:bg-gray-950/30">
                        <dt class="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400">Last Failure</dt>
                        <dd class="mt-2 text-sm font-medium text-gray-950 dark:text-white">{{ $summary['last_failed_at'] ?? 'n/a' }}</dd>
                    </div>
                </dl>

                @if ($isUnavailable && filled($summary['error'] ?? null))
                    <p class="mt-4 text-sm text-rose-700 dark:text-rose-300">
                        {{ $summary['error'] }}
                    </p>
                @endif
            </div>
        @endforeach
    </div>
@else
    <div class="mt-6 rounded-2xl border border-dashed border-gray-200 px-4 py-5 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
        Очереди пока не удалось прочитать. Если блок остаётся пустым, стоит проверить Livewire и текущее состояние RabbitMQ.
    </div>
@endif
