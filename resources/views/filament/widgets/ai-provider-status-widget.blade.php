@php
    $statusColor = $record?->statusColor() ?? 'gray';
    $statusClasses = match ($statusColor) {
        'success' => 'bg-emerald-500/10 text-emerald-700 ring-1 ring-inset ring-emerald-500/20 dark:bg-emerald-400/10 dark:text-emerald-300 dark:ring-emerald-400/20',
        'warning' => 'bg-amber-500/10 text-amber-700 ring-1 ring-inset ring-amber-500/20 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/20',
        'danger' => 'bg-rose-500/10 text-rose-700 ring-1 ring-inset ring-rose-500/20 dark:bg-rose-400/10 dark:text-rose-300 dark:ring-rose-400/20',
        default => 'bg-gray-500/10 text-gray-700 ring-1 ring-inset ring-gray-500/20 dark:bg-gray-400/10 dark:text-gray-300 dark:ring-gray-400/20',
    };

    $plan = is_string($record?->plan_type) && $record->plan_type !== ''
        ? strtoupper($record->plan_type)
        : null;
    $reasoning = $record?->default_reasoning_effort ?? 'model_default';
    $primaryUsed = $record?->rateLimitUsedPercent();
    $weeklyUsed = $record?->weeklyRateLimitUsedPercent();
    $primaryReset = $record?->rateLimitResetAt()?->setTimezone(config('app.timezone'))->format('d.m H:i');
    $weeklyReset = $record?->weeklyRateLimitResetAt()?->setTimezone(config('app.timezone'))->format('d.m H:i');
@endphp

<x-filament-widgets::widget
    wire:poll.10s
    class="fi-ai-provider-status-widget"
>
    <x-filament::section>
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(320px,0.9fr)]">
            <div class="space-y-5">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex items-start gap-4">
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-amber-500/12 text-sm font-semibold uppercase tracking-[0.28em] text-amber-600 dark:bg-amber-400/10 dark:text-amber-300">
                            AI
                        </div>

                        <div class="space-y-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-[0.7rem] font-semibold uppercase tracking-[0.24em] text-gray-500 dark:text-gray-400">
                                    AI Provider
                                </p>

                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClasses }}">
                                    {{ $record?->statusLabel() ?? 'Missing' }}
                                </span>

                                @if ($plan)
                                    <span class="inline-flex items-center rounded-full bg-gray-950 px-2.5 py-1 text-xs font-semibold tracking-[0.2em] text-white dark:bg-white dark:text-gray-950">
                                        {{ $plan }}
                                    </span>
                                @endif
                            </div>

                            <div class="space-y-1">
                                <h3 class="text-xl font-semibold tracking-tight text-gray-950 dark:text-white">
                                    {{ $record?->display_name ?? 'ChatGPT Codex' }}
                                </h3>

                                <p class="text-sm text-gray-600 dark:text-gray-300">
                                    {{ $record?->account_email ?? 'Нет активного ChatGPT аккаунта. Авторизуй провайдер, чтобы анализ новостей работал в очередях и песочнице.' }}
                                </p>
                            </div>

                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Текущий runtime использует модель <span class="font-medium text-gray-800 dark:text-gray-100">{{ $record?->default_model ?? 'gpt-5.4-mini' }}</span>
                                с уровнем reasoning <span class="font-medium text-gray-800 dark:text-gray-100">{{ $reasoning }}</span>.
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <x-filament::button
                            tag="a"
                            color="gray"
                            icon="heroicon-o-cog-6-tooth"
                            :href="$providerUrl"
                        >
                            Open Providers
                        </x-filament::button>

                        @if ($record?->auth_url)
                            <x-filament::button
                                tag="a"
                                color="warning"
                                icon="heroicon-o-arrow-top-right-on-square"
                                :href="$record->auth_url"
                                target="_blank"
                                rel="noreferrer"
                            >
                                Open Auth URL
                            </x-filament::button>
                        @endif
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-2xl bg-slate-50 px-4 py-4 dark:bg-white/5">
                        <p class="text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                            Model
                        </p>
                        <p class="mt-2 text-base font-semibold text-gray-950 dark:text-white">
                            {{ $record?->default_model ?? 'gpt-5.4-mini' }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Основная модель анализа новостей.
                        </p>
                    </div>

                    <div class="rounded-2xl bg-slate-50 px-4 py-4 dark:bg-white/5">
                        <p class="text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                            Reasoning
                        </p>
                        <p class="mt-2 text-base font-semibold text-gray-950 dark:text-white">
                            {{ $reasoning }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Профиль размышлений для `codex exec`.
                        </p>
                    </div>

                    <div class="rounded-2xl bg-slate-50 px-4 py-4 dark:bg-white/5">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                                Used
                            </p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $primaryUsed !== null ? $primaryUsed.'%' : 'n/a' }}
                            </p>
                        </div>

                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
                            <div
                                class="h-full rounded-full bg-amber-500 transition-all duration-500 dark:bg-amber-400"
                                style="width: {{ max(0, min($primaryUsed ?? 0, 100)) }}%;"
                            ></div>
                        </div>

                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            5h окно{{ $primaryReset ? ' · reset '.$primaryReset : '' }}
                        </p>
                    </div>

                    <div class="rounded-2xl bg-slate-50 px-4 py-4 dark:bg-white/5">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                                Week
                            </p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $weeklyUsed !== null ? $weeklyUsed.'%' : 'n/a' }}
                            </p>
                        </div>

                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
                            <div
                                class="h-full rounded-full bg-sky-500 transition-all duration-500 dark:bg-sky-400"
                                style="width: {{ max(0, min($weeklyUsed ?? 0, 100)) }}%;"
                            ></div>
                        </div>

                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            7d окно{{ $weeklyReset ? ' · reset '.$weeklyReset : '' }}
                        </p>
                    </div>
                </div>

                @if ($record?->last_error_message)
                    <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-300">
                        {{ $record->last_error_message }}
                    </div>
                @endif
            </div>

            <div class="space-y-4">
                <div class="rounded-2xl bg-gray-950 px-5 py-5 text-white dark:bg-white/5">
                    <p class="text-[0.7rem] font-semibold uppercase tracking-[0.24em] text-white/60">
                        Runtime Snapshot
                    </p>

                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-white/60">Status</dt>
                            <dd class="font-medium text-white">{{ $record?->statusLabel() ?? 'Missing' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-white/60">Account</dt>
                            <dd class="truncate text-right font-medium text-white">{{ $record?->account_email ?? 'not connected' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-white/60">Parallel Jobs</dt>
                            <dd class="font-medium text-white">{{ $record?->max_parallel_jobs ?? 'n/a' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-white/60">Profile</dt>
                            <dd class="font-medium text-white">{{ $record?->slug ?? 'chatgpt-default' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-2xl border border-dashed border-gray-200 px-5 py-5 dark:border-gray-800">
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">
                        Что это значит
                    </p>
                    <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">
                        Этот провайдер используется песочницей и очередями Intelligence. Если статус не `Authenticated`,
                        анализ новостей уйдёт в эвристический fallback.
                    </p>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
