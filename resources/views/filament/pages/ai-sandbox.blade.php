<x-filament-panels::page>
    @php
        /** @var array<string, mixed>|null $result */
        $result = $this->result;
        /** @var array<string, mixed>|null $analysis */
        $analysis = is_array($result['analysis'] ?? null) ? $result['analysis'] : null;
        /** @var array<string, mixed>|null $provider */
        $provider = is_array($result['provider'] ?? null) ? $result['provider'] : null;
        $selectedProvider = $this->selectedProviderRecord();
        $metadataJson = is_array($analysis['analysis_metadata'] ?? null)
            ? json_encode($analysis['analysis_metadata'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;
    @endphp

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(340px,1fr)]">
        <div class="space-y-6">
            <form wire:submit="run" class="space-y-6">
                {{ $this->form }}

                <div class="flex flex-wrap items-center gap-3">
                    <x-filament::button type="submit" icon="heroicon-o-play">
                        Run AI Test
                    </x-filament::button>

                    @if ($result || $errorMessage)
                        <x-filament::button type="button" color="gray" wire:click="clearResult">
                            Clear Result
                        </x-filament::button>
                    @endif
                </div>
            </form>
        </div>

        <div class="space-y-6">
            <x-filament::section heading="Provider Snapshot">
                <div class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                    @if ($selectedProvider)
                        <p><span class="font-medium">Provider:</span> {{ $selectedProvider->display_name }}</p>
                        <p><span class="font-medium">Status:</span> {{ $selectedProvider->statusLabel() }}</p>
                        <p><span class="font-medium">Plan:</span> {{ $selectedProvider->plan_type ?? 'n/a' }}</p>
                        <p><span class="font-medium">Model:</span> {{ $selectedProvider->default_model }}</p>
                        <p><span class="font-medium">Reasoning:</span> {{ $selectedProvider->default_reasoning_effort ?? 'model_default' }}</p>
                        <p><span class="font-medium">5h used:</span> {{ $selectedProvider->rateLimitUsedPercent() !== null ? $selectedProvider->rateLimitUsedPercent() . '%' : 'n/a' }}</p>
                        <p><span class="font-medium">Week used:</span> {{ $selectedProvider->weeklyRateLimitUsedPercent() !== null ? $selectedProvider->weeklyRateLimitUsedPercent() . '%' : 'n/a' }}</p>
                    @else
                        <p class="text-gray-500 dark:text-gray-400">Выбери провайдера, и здесь появится его текущая конфигурация.</p>
                    @endif
                </div>
            </x-filament::section>

            @if ($errorMessage)
                <x-filament::section heading="Last Error">
                    <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200">
                        {{ $errorMessage }}
                    </div>
                </x-filament::section>
            @endif

            @if ($result && $analysis && $provider)
                <x-filament::section heading="AI Response">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl bg-amber-50 px-4 py-3 dark:bg-amber-500/10">
                            <p class="text-xs uppercase tracking-wide text-amber-700 dark:text-amber-200">Generated Title</p>
                            <p class="mt-2 text-sm font-medium text-gray-900 dark:text-white">{{ $analysis['generated_title'] ?: 'n/a' }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3 dark:bg-white/5">
                            <p class="text-xs uppercase tracking-wide text-slate-600 dark:text-slate-300">Category</p>
                            <p class="mt-2 text-sm font-medium text-gray-900 dark:text-white">{{ $analysis['category'] ?: 'n/a' }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3 dark:bg-white/5">
                            <p class="text-xs uppercase tracking-wide text-slate-600 dark:text-slate-300">Sentiment</p>
                            <p class="mt-2 text-sm font-medium text-gray-900 dark:text-white">{{ $analysis['sentiment'] }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3 dark:bg-white/5">
                            <p class="text-xs uppercase tracking-wide text-slate-600 dark:text-slate-300">Elapsed</p>
                            <p class="mt-2 text-sm font-medium text-gray-900 dark:text-white">{{ $result['elapsed_ms'] }} ms</p>
                        </div>
                    </div>

                    <div class="mt-4 space-y-2 text-sm text-gray-700 dark:text-gray-200">
                        <p><span class="font-medium">Tags:</span> {{ filled($analysis['tags']) ? implode(', ', $analysis['tags']) : 'n/a' }}</p>
                        <p><span class="font-medium">Run at:</span> {{ $result['ran_at'] }}</p>
                        <p><span class="font-medium">Provider/model:</span> {{ $provider['display_name'] }} / {{ $provider['model'] }}</p>
                    </div>
                </x-filament::section>

                <x-filament::section heading="Translated Content">
                    <div class="rounded-2xl bg-slate-50 px-4 py-4 text-sm leading-6 text-gray-900 dark:bg-white/5 dark:text-gray-100">
                        <div class="whitespace-pre-wrap">{{ $analysis['translated_content'] }}</div>
                    </div>
                </x-filament::section>

                <x-filament::section heading="Analysis Metadata">
                    <pre class="overflow-x-auto rounded-2xl bg-gray-950 px-4 py-4 text-xs leading-6 text-gray-100">{{ $metadataJson }}</pre>
                </x-filament::section>
            @else
                <x-filament::section heading="Sandbox Result">
                    <div class="rounded-2xl border border-dashed border-gray-200 px-4 py-5 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        Запусти тест, чтобы увидеть реальный ответ провайдера на введённый новостной текст.
                    </div>
                </x-filament::section>
            @endif
        </div>
    </div>
</x-filament-panels::page>
