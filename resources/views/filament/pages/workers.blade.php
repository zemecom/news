@php
    $workers = is_array($workers ?? null) ? $workers : [];
    $selectedRuntime = is_string($selectedRuntime ?? null) ? $selectedRuntime : null;
    $selectedQueue = is_string($selectedQueue ?? null) ? $selectedQueue : null;
    $dockerControl = is_array($dockerControl ?? null) ? $dockerControl : [];
    $failedJobs = is_array($failedJobs ?? null) ? $failedJobs : [];
    $queuedJobs = is_array($queuedJobs ?? null) ? $queuedJobs : [];
    $logTail = is_array($logTail ?? null) ? $logTail : ['lines' => [], 'error' => null];
    $dockerConfigured = (bool) ($dockerControl['configured'] ?? false);
    $selectedWorker = collect($workers)->first(static fn (array $worker): bool => ($worker['runtime'] ?? null) === $selectedRuntime);
    $runningWorkers = collect($workers)->where('health', 'running')->count();
    $degradedWorkers = collect($workers)->where('health', 'degraded')->count();
    $stoppedWorkers = collect($workers)->where('health', 'stopped')->count();
    $totalMessages = (int) collect($workers)->sum(static fn (array $worker): int => (int) ($worker['message_count'] ?? 0));
    $totalFailed = (int) collect($workers)->sum(static fn (array $worker): int => (int) ($worker['failed_count'] ?? 0));
    $totalConsumers = (int) collect($workers)->sum(static fn (array $worker): int => (int) ($worker['consumer_count'] ?? 0));
    $dockerBackend = is_string($dockerControl['backend'] ?? null) ? $dockerControl['backend'] : null;
    $showDockerBackendLabel = $dockerConfigured && filled($dockerBackend) && strtolower($dockerBackend) !== 'not-configured';
    $logTailUnavailableBecauseDockerIsOff = ! $dockerConfigured && filled($logTail['error'] ?? null);
@endphp

<x-filament-panels::page>
    <div wire:poll.10s="refreshWorkersPage" class="space-y-6">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-950/20">
                <div class="text-xs font-medium uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">Managed Runtimes</div>
                <div class="mt-2 flex items-end justify-between gap-3">
                    <div class="text-2xl font-semibold text-gray-950 dark:text-white">{{ count($workers) }}</div>
                    <div class="text-right text-xs text-gray-500 dark:text-gray-400">
                        <div>{{ $runningWorkers }} running</div>
                        <div>{{ $degradedWorkers }} degraded</div>
                        <div>{{ $stoppedWorkers }} stopped</div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-950/20">
                <div class="text-xs font-medium uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">Queue Backlog</div>
                <div class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $totalMessages }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $totalConsumers }} active consumers</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-950/20">
                <div class="text-xs font-medium uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">Failed Jobs</div>
                <div class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $totalFailed }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Recent failures are listed below.</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-950/20">
                <div class="text-xs font-medium uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">Supervisor</div>
                <div class="mt-2 flex items-center gap-2">
                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $dockerConfigured ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-200' : 'bg-sky-100 text-sky-700 dark:bg-sky-400/10 dark:text-sky-200' }}">
                        {{ $dockerConfigured ? 'configured' : 'not configured' }}
                    </span>
                    @if ($showDockerBackendLabel)
                        <span class="text-xs font-medium uppercase tracking-[0.12em] text-gray-600 dark:text-gray-300">{{ $dockerBackend }}</span>
                    @endif
                </div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Control-plane for runtime actions and logs.</div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-12">
            <div class="space-y-6 lg:col-span-7 xl:col-span-8">
                <x-filament::section heading="Worker Runtime" description="Runtime-состояние, bounded queue actions и diagnostics для управляемых воркеров.">
                    <div class="grid gap-3" style="grid-template-columns: repeat(3, minmax(0, 1fr));">
                        @foreach ($workers as $worker)
                            @php
                                $runtime = is_string($worker['runtime'] ?? null) ? $worker['runtime'] : 'unknown';
                                $queue = is_string($worker['queue'] ?? null) ? $worker['queue'] : 'n/a';
                                $isSelected = $selectedRuntime === $runtime;
                                $health = is_string($worker['health'] ?? null) ? $worker['health'] : 'unavailable';
                                $healthClasses = match ($health) {
                                    'running' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-200',
                                    'degraded' => 'bg-amber-100 text-amber-700 dark:bg-amber-400/10 dark:text-amber-200',
                                    'stopped' => 'bg-slate-100 text-slate-700 dark:bg-slate-400/10 dark:text-slate-200',
                                    'not_configured' => 'bg-sky-100 text-sky-700 dark:bg-sky-400/10 dark:text-sky-200',
                                    default => 'bg-rose-100 text-rose-700 dark:bg-rose-400/10 dark:text-rose-200',
                                };
                            @endphp

                            <div class="min-w-0 overflow-hidden rounded-xl border {{ $isSelected ? 'border-primary-400/70 bg-primary-50/40 dark:border-primary-400/40 dark:bg-primary-400/[0.04]' : 'border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950/20' }} px-4 py-3 shadow-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <button type="button" wire:click="selectRuntime('{{ $runtime }}')" class="text-left">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $runtime }}</span>
                                                @if ($isSelected)
                                                    <span class="inline-flex items-center rounded-full bg-primary-100 px-2 py-0.5 text-[11px] font-medium text-primary-700 dark:bg-primary-400/10 dark:text-primary-200">
                                                        Selected
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="mt-2 inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-[11px] font-medium text-gray-700 dark:bg-white/5 dark:text-gray-300">
                                                {{ $queue }}
                                            </div>
                                        </button>
                                    </div>

                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold capitalize {{ $healthClasses }}">
                                        {{ str_replace('_', ' ', $health) }}
                                    </span>
                                </div>

                                <dl class="mt-4 space-y-2">
                                    <div class="flex items-center justify-between gap-2 text-sm">
                                        <dt class="text-[11px] uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">Messages</dt>
                                        <dd class="font-semibold text-gray-950 dark:text-white">{{ $worker['message_count'] ?? 'n/a' }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-2 text-sm">
                                        <dt class="text-[11px] uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">Consumers</dt>
                                        <dd class="font-semibold text-gray-950 dark:text-white">{{ $worker['consumer_count'] ?? 'n/a' }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-2 text-sm">
                                        <dt class="text-[11px] uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">Failed</dt>
                                        <dd class="font-semibold text-gray-950 dark:text-white">{{ $worker['failed_count'] ?? 0 }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-2 text-sm">
                                        <dt class="text-[11px] uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">Memory</dt>
                                        <dd class="font-medium text-gray-950 dark:text-white">{{ $worker['memory_human'] ?? 'n/a' }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-2 text-sm">
                                        <dt class="text-[11px] uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">CPU</dt>
                                        <dd class="font-medium text-gray-950 dark:text-white">{{ $worker['cpu_percent'] ?? 'n/a' }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-2 text-sm">
                                        <dt class="text-[11px] uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">Uptime</dt>
                                        <dd class="font-medium text-gray-950 dark:text-white">{{ $worker['uptime_human'] ?? 'n/a' }}</dd>
                                    </div>
                                </dl>

                                @if ($health !== 'not_configured' || filled($worker['last_processed_job'] ?? null) || filled($worker['last_heartbeat_at'] ?? null))
                                    <div class="mt-3 rounded-lg bg-gray-50 px-3 py-2 text-xs leading-5 text-gray-600 dark:bg-white/[0.04] dark:text-gray-300">
                                        @if (filled($worker['last_error_summary'] ?? null) && $health !== 'not_configured')
                                            {{ $worker['last_error_summary'] }}
                                        @elseif (filled($worker['last_processed_job'] ?? null))
                                            Last processed: {{ $worker['last_processed_job'] }}
                                        @elseif (filled($worker['last_heartbeat_at'] ?? null))
                                            Last heartbeat: {{ $worker['last_heartbeat_at'] }}
                                        @else
                                            Waiting for runtime telemetry.
                                        @endif
                                    </div>
                                @endif

                                <div class="mt-3 flex flex-wrap gap-2">
                                    <x-filament::button size="sm" icon="heroicon-m-play" wire:click="startWorker('{{ $runtime }}')">
                                        Start
                                    </x-filament::button>

                                    <x-filament::button
                                        size="sm"
                                        color="gray"
                                        icon="heroicon-m-stop"
                                        x-on:click="if (! window.confirm('Stop {{ $runtime }}?')) { $event.stopImmediatePropagation() }"
                                        wire:click="stopWorker('{{ $runtime }}')"
                                    >
                                        Stop
                                    </x-filament::button>

                                    <x-filament::button
                                        size="sm"
                                        color="warning"
                                        icon="heroicon-m-arrow-path"
                                        x-on:click="if (! window.confirm('Restart {{ $runtime }}?')) { $event.stopImmediatePropagation() }"
                                        wire:click="restartWorker('{{ $runtime }}')"
                                    >
                                        Restart
                                    </x-filament::button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>

                <x-filament::section heading="Queue Preview" description="Head snapshot выбранной очереди через RabbitMQ Management API.">
                    @if ($queuedJobs !== [])
                        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                                    <thead class="bg-slate-50 dark:bg-white/5">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">#</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Job</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Attempts</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Payload</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-900 dark:bg-gray-950/20">
                                        @foreach ($queuedJobs as $job)
                                            <tr>
                                                <td class="px-4 py-3 align-top text-gray-700 dark:text-gray-200">{{ $job['position'] }}</td>
                                                <td class="px-4 py-3 align-top text-gray-700 dark:text-gray-200">
                                                    <div class="font-medium text-gray-950 dark:text-white">{{ $job['display_name'] }}</div>
                                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $job['job_uuid'] ?? 'n/a' }}</div>
                                                </td>
                                                <td class="px-4 py-3 align-top text-gray-700 dark:text-gray-200">{{ $job['attempts'] }}</td>
                                                <td class="px-4 py-3 align-top text-gray-700 dark:text-gray-200">{{ $job['payload_preview'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @else
                        <div class="rounded-xl border border-dashed border-gray-200 px-4 py-5 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            В `{{ $selectedQueue ?? 'selected queue' }}` сейчас нет ожидающих сообщений.
                        </div>
                    @endif
                </x-filament::section>

                <x-filament::section heading="Recent Failures" description="Последние ошибки по рабочим очередям RabbitMQ.">
                    @if ($failedJobs !== [])
                        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
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
                        <div class="rounded-xl border border-dashed border-gray-200 px-4 py-5 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            По рабочим очередям пока нет записей в `failed_jobs`.
                        </div>
                    @endif
                </x-filament::section>
            </div>

            <div class="space-y-6 lg:col-span-5 xl:col-span-4 lg:sticky lg:top-6 lg:self-start">
                <x-filament::section heading="Runtime Actions" description="Soft restart глобален для всех Laravel queue workers; drain всегда bounded.">
                    <div class="space-y-3">
                        <div class="min-w-0 rounded-xl border border-gray-200 bg-white px-3 py-3 dark:border-gray-800 dark:bg-gray-950/20">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-gray-950 dark:text-white">{{ $selectedWorker['runtime'] ?? 'No runtime selected' }}</div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $selectedWorker['queue'] ?? 'Select a runtime to inspect its queue actions.' }}</div>
                                </div>

                                @if (filled($selectedWorker['health'] ?? null))
                                    @php
                                        $selectedHealth = (string) ($selectedWorker['health'] ?? 'unavailable');
                                        $selectedHealthClasses = match ($selectedHealth) {
                                            'running' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-200',
                                            'degraded' => 'bg-amber-100 text-amber-700 dark:bg-amber-400/10 dark:text-amber-200',
                                            'stopped' => 'bg-slate-100 text-slate-700 dark:bg-slate-400/10 dark:text-slate-200',
                                            'not_configured' => 'bg-sky-100 text-sky-700 dark:bg-sky-400/10 dark:text-sky-200',
                                            default => 'bg-rose-100 text-rose-700 dark:bg-rose-400/10 dark:text-rose-200',
                                        };
                                    @endphp

                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold capitalize {{ $selectedHealthClasses }}">
                                        {{ str_replace('_', ' ', $selectedHealth) }}
                                    </span>
                                @endif
                            </div>

                            <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-xs">
                                <div class="flex items-center justify-between gap-2">
                                    <dt class="text-[11px] uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">Messages</dt>
                                    <dd class="font-medium text-gray-950 dark:text-white">{{ $selectedWorker['message_count'] ?? 'n/a' }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <dt class="text-[11px] uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">Consumers</dt>
                                    <dd class="font-medium text-gray-950 dark:text-white">{{ $selectedWorker['consumer_count'] ?? 'n/a' }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <dt class="text-[11px] uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">Failed</dt>
                                    <dd class="font-medium text-gray-950 dark:text-white">{{ $selectedWorker['failed_count'] ?? 0 }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <dt class="text-[11px] uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">Heartbeat</dt>
                                    <dd class="max-w-[11rem] truncate font-medium text-gray-950 dark:text-white" title="{{ $selectedWorker['last_heartbeat_at'] ?? 'n/a' }}">{{ $selectedWorker['last_heartbeat_at'] ?? 'n/a' }}</dd>
                                </div>
                            </dl>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <x-filament::button size="sm" color="gray" icon="heroicon-m-arrow-path" wire:click="refreshWorkersPage">
                                Refresh
                            </x-filament::button>

                            <x-filament::button
                                size="sm"
                                color="warning"
                                icon="heroicon-m-arrow-path"
                                x-on:click="if (! window.confirm('Broadcast Laravel queue:restart to all workers?')) { $event.stopImmediatePropagation() }"
                                wire:click="softRestartWorkers"
                            >
                                Soft restart
                            </x-filament::button>

                            @if ($selectedQueue !== null)
                                <x-filament::button size="sm" color="success" icon="heroicon-m-play" wire:click="runOneJob('{{ $selectedQueue }}')">
                                    Run 1 Job
                                </x-filament::button>

                                <x-filament::button size="sm" color="info" icon="heroicon-m-bolt" wire:click="runUntilEmpty('{{ $selectedQueue }}')">
                                    Run until empty
                                </x-filament::button>

                                <x-filament::button
                                    size="sm"
                                    color="danger"
                                    icon="heroicon-m-trash"
                                    x-on:click="if (! window.confirm('Purge {{ $selectedQueue }}?')) { $event.stopImmediatePropagation() }"
                                    wire:click="purgeQueue('{{ $selectedQueue }}')"
                                >
                                    Purge Queue
                                </x-filament::button>
                            @endif
                        </div>
                    </div>
                </x-filament::section>

                <x-filament::section heading="Docker Control" description="Container lifecycle идёт через supervisor adapter, а не через доступ app-container к Docker socket.">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $dockerConfigured ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-200' : 'bg-sky-100 text-sky-700 dark:bg-sky-400/10 dark:text-sky-200' }}">
                                {{ $dockerConfigured ? 'Configured' : 'Not configured' }}
                            </span>
                            @if ($showDockerBackendLabel)
                                <span class="text-xs font-medium uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">{{ $dockerBackend }}</span>
                            @endif
                        </div>

                        @if (filled($dockerControl['message'] ?? null))
                            <div class="rounded-xl border border-sky-200/70 bg-sky-50/70 px-4 py-3 text-sm text-slate-700 dark:border-sky-400/10 dark:bg-sky-400/[0.08] dark:text-slate-200">
                                {{ $dockerControl['message'] }}
                            </div>
                        @endif

                        @if (! $dockerConfigured && $workers !== [])
                            <div class="space-y-3">
                                <div class="rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-800">
                                    <div class="text-[11px] uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">Bring Up</div>
                            <div class="mt-2 overflow-x-auto font-mono text-sm text-gray-950 dark:text-white">{{ $workers[0]['operator_commands']['start_all'] ?? 'make worker-up' }}</div>
                                </div>

                                <div class="rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-800">
                                    <div class="text-[11px] uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">Selected Runtime</div>
                                    <div class="mt-2 overflow-x-auto font-mono text-sm text-gray-950 dark:text-white">
                                        @foreach ($workers as $worker)
                                            @if (($worker['runtime'] ?? null) === $selectedRuntime)
                                                {{ $worker['operator_commands']['restart_runtime'] ?? 'docker compose restart '.$selectedRuntime }}
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </x-filament::section>

                <x-filament::section heading="Log Tail" description="Supervisor log tail для выбранного runtime.">
                    @if ($logTailUnavailableBecauseDockerIsOff)
                        <div class="rounded-xl border border-dashed border-gray-200 px-4 py-5 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            Log tail станет доступен после настройки supervisor adapter.
                        </div>
                    @elseif (filled($logTail['error'] ?? null))
                        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-4 text-sm text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-200">
                            {{ $logTail['error'] }}
                        </div>
                    @elseif (($logTail['lines'] ?? []) !== [])
                        <div class="max-h-[28rem] overflow-auto rounded-xl bg-gray-950 p-4 font-mono text-xs leading-6 text-emerald-200">
                            @foreach ($logTail['lines'] as $line)
                                <div>{{ $line }}</div>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-xl border border-dashed border-gray-200 px-4 py-5 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            Для выбранного runtime пока нет доступных log lines.
                        </div>
                    @endif
                </x-filament::section>
            </div>
        </div>
    </div>
</x-filament-panels::page>
