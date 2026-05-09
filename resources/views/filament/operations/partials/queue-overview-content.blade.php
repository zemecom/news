@php
    $summaries = is_array($summaries ?? null) ? $summaries : [];
@endphp

<div class="space-y-2">
    <div>
        <p class="text-[0.7rem] font-semibold uppercase tracking-[0.24em] text-gray-500 dark:text-gray-400">
            Queue Overview
        </p>
        <h3 class="mt-2 text-xl font-semibold tracking-tight text-gray-950 dark:text-white">
            Queues & Analysis Health
        </h3>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
            Live snapshot of RabbitMQ queues served by the shared `worker` fleet.
        </p>
    </div>

    <p class="max-w-3xl text-xs text-gray-500 dark:text-gray-400">
        Polling Laravel workers may keep `consumer_count = 0`, so this block is a broker snapshot, not a process supervisor.
    </p>
</div>

@if ($summaries !== [])
    <div class="mt-5 overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-800">
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
                        <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Error</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-900 dark:bg-gray-950/20">
                    @foreach ($summaries as $summary)
                        @php
                            $isUnavailable = ($summary['status'] ?? 'ok') !== 'ok';
                            $hasConsumers = ((int) ($summary['consumer_count'] ?? 0)) > 0;
                            $stateLabel = $isUnavailable ? 'Unavailable' : ($hasConsumers ? 'Consumer active' : 'Broker OK');
                            $badgeClasses = $isUnavailable
                                ? 'bg-rose-100 text-rose-700 dark:bg-rose-400/10 dark:text-rose-200'
                                : ($hasConsumers
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-200'
                                    : 'bg-sky-100 text-sky-700 dark:bg-sky-400/10 dark:text-sky-200');
                        @endphp

                        <tr>
                            <td class="px-4 py-3 align-top font-medium text-gray-950 dark:text-white">{{ $summary['queue'] }}</td>
                            <td class="px-4 py-3 align-top">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $badgeClasses }}">
                                    {{ $stateLabel }}
                                </span>
                            </td>
                            <td class="px-4 py-3 align-top text-gray-700 dark:text-gray-200">{{ $summary['message_count'] ?? 'n/a' }}</td>
                            <td class="px-4 py-3 align-top text-gray-700 dark:text-gray-200">{{ $summary['consumer_count'] ?? 'n/a' }}</td>
                            <td class="px-4 py-3 align-top text-gray-700 dark:text-gray-200">{{ $summary['failed_count'] }}</td>
                            <td class="px-4 py-3 align-top text-gray-700 dark:text-gray-200">{{ $summary['last_failed_at'] ?? 'n/a' }}</td>
                            <td class="px-4 py-3 align-top text-gray-600 dark:text-gray-300">
                                {{ filled($summary['error'] ?? null) ? $summary['error'] : 'n/a' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@else
    <div class="mt-6 rounded-2xl border border-dashed border-gray-200 px-4 py-5 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
        Очереди пока не удалось прочитать. Если блок остаётся пустым, стоит проверить Livewire и текущее состояние RabbitMQ.
    </div>
@endif
