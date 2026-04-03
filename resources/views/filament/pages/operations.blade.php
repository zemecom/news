@php
    $queueSummaries = is_array($queueSummaries ?? null) ? $queueSummaries : [];
    $failedJobs = is_array($failedJobs ?? null) ? $failedJobs : [];
    $queuedJobs = is_array($queuedJobs ?? null) ? $queuedJobs : [];
    $selectedQueue = is_string($selectedQueue ?? null) ? $selectedQueue : null;
    $queuedJobsError = is_string($queuedJobsError ?? null) ? $queuedJobsError : null;
@endphp

<x-filament-panels::page>
    <div wire:poll.10s="refreshOperationsPage" class="space-y-6">
        <x-filament::section heading="Queues & Analysis Health" description="Сводка по очередям RabbitMQ, выбор очереди и preview head задач в одном месте.">
            <div class="space-y-4">
                <div class="max-w-3xl text-xs text-gray-500 dark:text-gray-400">
                    Polling Laravel workers may keep `consumer_count = 0`, so this block is a broker snapshot, not a process supervisor.
                </div>

                <div class="overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-800">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                            <thead class="bg-slate-50 dark:bg-white/5">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Queue</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Status</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Messages</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Consumers</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Failed</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Last Failure</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-900 dark:bg-gray-950/20">
                                @foreach ($queueSummaries as $summary)
                                    @php
                                        $queueName = $summary['queue'] ?? '';
                                        $isSelected = $selectedQueue === $queueName;
                                        $isUnavailable = ($summary['status'] ?? 'ok') !== 'ok';
                                        $hasConsumers = ((int) ($summary['consumer_count'] ?? 0)) > 0;
                                        $stateLabel = $isUnavailable ? 'Unavailable' : ($hasConsumers ? 'Consumer active' : 'Broker OK');
                                        $badgeClasses = $isUnavailable
                                            ? 'bg-rose-100 text-rose-700 dark:bg-rose-400/10 dark:text-rose-200'
                                            : ($hasConsumers
                                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-200'
                                                : 'bg-sky-100 text-sky-700 dark:bg-sky-400/10 dark:text-sky-200');
                                    @endphp

                                    <tr
                                        wire:click="selectQueue('{{ $queueName }}')"
                                        style="cursor: pointer;"
                                        class="{{ $isSelected ? 'bg-amber-50/70 dark:bg-amber-400/[0.08]' : '' }} cursor-pointer transition hover:bg-slate-50 dark:hover:bg-white/[0.04]"
                                    >
                                        <td class="px-4 py-3 align-top font-medium text-gray-950 dark:text-white">{{ $queueName }}</td>
                                        <td class="px-4 py-3 align-top">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $badgeClasses }}">
                                                {{ $stateLabel }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 align-top text-gray-700 dark:text-gray-200">{{ $summary['message_count'] ?? 'n/a' }}</td>
                                        <td class="px-4 py-3 align-top text-gray-700 dark:text-gray-200">{{ $summary['consumer_count'] ?? 'n/a' }}</td>
                                        <td class="px-4 py-3 align-top text-gray-700 dark:text-gray-200">{{ $summary['failed_count'] }}</td>
                                        <td class="px-4 py-3 align-top text-gray-700 dark:text-gray-200">{{ $summary['last_failed_at'] ?? 'n/a' }}</td>
                                        <td class="px-4 py-3 align-top">
                                            <button
                                                type="button"
                                                wire:click.stop="selectQueue('{{ $queueName }}')"
                                                class="text-xs font-semibold text-amber-700 transition hover:text-amber-600 dark:text-amber-300 dark:hover:text-amber-200"
                                            >
                                                {{ $isSelected ? 'Selected' : 'Open queue' }}
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                @if ($selectedQueue !== null)
                    <div class="rounded-2xl border border-gray-200 bg-gray-50/80 px-4 py-3 dark:border-gray-800 dark:bg-white/[0.03]">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <div class="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">
                                    Selected Queue
                                </div>
                                <div class="mt-1 text-base font-semibold text-gray-950 dark:text-white">
                                    {{ $selectedQueue }}
                                </div>
                                <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    Ниже показан preview head сообщений и действия для выбранной очереди.
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-3">
                                <x-filament::button
                                    type="button"
                                    size="sm"
                                    icon="heroicon-o-play"
                                    wire:click="processOneQueueJob('{{ $selectedQueue }}')"
                                    wire:confirm="Запустить one-step обработку одной задачи из {{ $selectedQueue }}?"
                                >
                                    Run 1 Job
                                </x-filament::button>

                                <x-filament::button
                                    type="button"
                                    color="danger"
                                    size="sm"
                                    icon="heroicon-o-trash"
                                    wire:click="purgeQueue('{{ $selectedQueue }}')"
                                    wire:confirm="Очистить очередь {{ $selectedQueue }}? Это удалит все ожидающие сообщения."
                                >
                                    Purge Queue
                                </x-filament::button>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="rounded-2xl border border-sky-200/70 bg-sky-50/70 px-4 py-3 text-sm leading-6 text-slate-700 dark:border-sky-400/10 dark:bg-sky-400/[0.08] dark:text-slate-200">
                    Это preview head сообщений через RabbitMQ Management API с requeue. Блок полезен для операционной диагностики, но это не строгий forensic dump и не полноценный process supervisor.
                </div>

                @if ($queuedJobsError !== null)
                    <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-4 text-sm text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-200">
                        {{ $queuedJobsError }}
                    </div>
                @elseif ($queuedJobs !== [])
                    <div class="overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-800">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                                <thead class="bg-slate-50 dark:bg-white/5">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">#</th>
                                        <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Job</th>
                                        <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Attempts</th>
                                        <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Redelivered</th>
                                        <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Routing</th>
                                        <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Payload</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-900 dark:bg-gray-950/20">
                                    @foreach ($queuedJobs as $job)
                                        <tr>
                                            <td class="px-4 py-3 align-top font-medium text-gray-950 dark:text-white">{{ $job['position'] }}</td>
                                            <td class="px-4 py-3 align-top text-gray-700 dark:text-gray-200">
                                                <div class="font-medium text-gray-950 dark:text-white">{{ $job['display_name'] }}</div>
                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $job['job_uuid'] ?? $job['job_name'] ?? 'n/a' }}
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 align-top text-gray-600 dark:text-gray-300">{{ $job['attempts'] }}</td>
                                            <td class="px-4 py-3 align-top text-gray-600 dark:text-gray-300">{{ $job['redelivered'] ? 'yes' : 'no' }}</td>
                                            <td class="px-4 py-3 align-top text-gray-600 dark:text-gray-300">
                                                <div>{{ $job['routing_key'] ?? 'n/a' }}</div>
                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $job['exchange'] ?? 'n/a' }}</div>
                                            </td>
                                            <td class="px-4 py-3 align-top text-gray-600 dark:text-gray-300">
                                                <div>{{ $job['payload_preview'] }}</div>
                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $job['payload_bytes'] }} bytes</div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    <div class="rounded-2xl border border-dashed border-gray-200 px-4 py-5 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
                        В `{{ $selectedQueue ?? 'selected queue' }}` сейчас нет ожидающих сообщений.
                    </div>
                @endif
            </div>
        </x-filament::section>

        <x-filament::section heading="Recent Failures" description="Последние ошибки по рабочим очередям RabbitMQ.">
            @if ($failedJobs !== [])
                <div class="overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-800">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                            <thead class="bg-slate-50 dark:bg-white/5">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Queue</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Job</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Failed At</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Exception</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-900 dark:bg-gray-950/20">
                                @foreach ($failedJobs as $job)
                                    <tr>
                                        <td class="px-4 py-3 align-top font-medium text-gray-950 dark:text-white">{{ $job['queue'] }}</td>
                                        <td class="px-4 py-3 align-top text-gray-700 dark:text-gray-200">{{ $job['display_name'] }}</td>
                                        <td class="px-4 py-3 align-top text-gray-600 dark:text-gray-300">{{ $job['failed_at'] }}</td>
                                        <td class="px-4 py-3 align-top text-gray-600 dark:text-gray-300">{{ $job['exception_summary'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="rounded-2xl border border-dashed border-gray-200 px-4 py-5 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
                    По `crawler_tasks`, `intelligence_tasks` и `media_tasks` пока нет записей в `failed_jobs`.
                </div>
            @endif
        </x-filament::section>

        <x-filament::section heading="How To Read This" description="Короткая памятка для операционной панели.">
            <div class="grid gap-4 lg:grid-cols-3">
                <div class="rounded-2xl bg-slate-50 px-4 py-4 text-sm leading-6 text-gray-700 dark:bg-white/5 dark:text-gray-200">
                    `Messages` показывает текущую глубину очереди. Если число растёт, а `Consumers` равен `0`, значит воркер для этой очереди не подключён.
                </div>
                <div class="rounded-2xl bg-slate-50 px-4 py-4 text-sm leading-6 text-gray-700 dark:bg-white/5 dark:text-gray-200">
                    `Failed Jobs` и `Recent Failures` читаются из таблицы `failed_jobs`, поэтому дают быстрый operational context без отдельного RabbitMQ UI.
                </div>
                <div class="rounded-2xl bg-slate-50 px-4 py-4 text-sm leading-6 text-gray-700 dark:bg-white/5 dark:text-gray-200">
                    AI readiness берётся из активного ChatGPT Codex account: статус, модель, reasoning и текущие rate limit snapshots.
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
