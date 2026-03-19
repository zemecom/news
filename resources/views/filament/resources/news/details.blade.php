@php
    /** @var \Modules\Catalog\Infrastructure\Persistence\Models\NewsItem $record */
    $publishedAt = $record->published_at?->setTimezone((string) config('app.timezone', 'UTC'))->toDateTimeString();
    $tags = is_array($record->tags) ? array_values($record->tags) : [];
    $hasGeneratedTitle = filled($record->title_generated);
    $hasOriginalContent = filled(trim((string) $record->content_original));
    $hasTranslatedContent = filled(trim((string) ($record->content_translated ?? '')));
    $hasAnalysisMetadata = $analysisMetadataJson !== 'null';
    $analysisRuntime = is_array($analysisRuntime ?? null) ? $analysisRuntime : [];
    $analysisTimeline = is_array($analysisTimeline ?? null) ? $analysisTimeline : [];
    $hasRuntimeTrace = $analysisRuntime !== [];
    $hasMediaPayload = $mediaJson !== 'null';
    $statusColor = match ($record->status) {
        'published' => 'success',
        'rejected' => 'danger',
        default => 'warning',
    };
    $sentimentTone = match (true) {
        $record->sentiment_score >= 4 => 'text-emerald-600 dark:text-emerald-300',
        $record->sentiment_score <= -4 => 'text-rose-600 dark:text-rose-300',
        default => 'text-gray-950 dark:text-white',
    };
    $analysisRuntimeStatus = is_string($analysisRuntime['status'] ?? null) ? $analysisRuntime['status'] : 'not_analyzed';
    $analysisRuntimeColor = match ($analysisRuntimeStatus) {
        'completed' => 'success',
        'running' => 'info',
        'queued', 'fallback' => 'warning',
        'failed' => 'danger',
        default => 'gray',
    };
@endphp

<div class="space-y-5">
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl bg-slate-50 px-4 py-4 dark:bg-white/5">
            <p class="text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Status</p>
            <div class="mt-3">
                <x-filament::badge :color="$statusColor">
                    {{ str($record->status)->headline() }}
                </x-filament::badge>
            </div>
        </div>
        <div class="rounded-2xl bg-slate-50 px-4 py-4 dark:bg-white/5">
            <p class="text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Published</p>
            <p class="mt-2 text-sm font-semibold text-gray-950 dark:text-white">{{ $publishedAt ?? 'n/a' }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Время публикации в часовом поясе приложения.</p>
        </div>
        <div class="rounded-2xl bg-slate-50 px-4 py-4 dark:bg-white/5">
            <p class="text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Language</p>
            <p class="mt-2 text-sm font-semibold text-gray-950 dark:text-white">{{ filled($sourceLanguage) ? strtoupper($sourceLanguage) : 'n/a' }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Язык, пришедший из источника.</p>
        </div>
        <div class="rounded-2xl bg-slate-50 px-4 py-4 dark:bg-white/5">
            <p class="text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Sentiment</p>
            <p class="mt-2 text-sm font-semibold {{ $sentimentTone }}">{{ $record->sentiment_score }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Оценка тональности от -10 до 10.</p>
        </div>
    </div>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1.15fr)_minmax(340px,0.85fr)]">
        <x-filament::section heading="Article Snapshot" description="Оригинальные и вычисленные атрибуты новости.">
            <div class="space-y-5">
                @if ($record->image_url)
                    <div class="overflow-hidden rounded-2xl">
                        <img src="{{ $record->image_url }}" alt="Preview image" class="h-56 w-full object-cover">
                    </div>
                @endif

                <div class="space-y-2">
                    <p class="text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Original Title</p>
                    <h3 class="text-lg font-semibold tracking-tight text-gray-950 dark:text-white">{{ $record->title_original }}</h3>
                </div>

                @if ($hasGeneratedTitle)
                    <div class="rounded-2xl bg-amber-50 px-4 py-4 dark:bg-amber-400/10">
                        <p class="text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-amber-700 dark:text-amber-200">Generated Title</p>
                        <p class="mt-2 text-sm leading-6 text-gray-900 dark:text-white">{{ $record->title_generated }}</p>
                    </div>
                @endif

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-4">
                        <div>
                            <p class="text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Source</p>
                            <p class="mt-2 text-sm font-medium text-gray-950 dark:text-white">{{ $record->source?->name ?? 'n/a' }}</p>
                        </div>

                        <div>
                            <p class="text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Source Link</p>
                            @if ($sourceLink)
                                <a href="{{ $sourceLink }}" target="_blank" rel="noreferrer" class="mt-2 inline-flex break-all text-sm font-medium text-amber-600 hover:text-amber-500 dark:text-amber-300 dark:hover:text-amber-200">
                                    {{ $sourceLink }}
                                </a>
                            @else
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">n/a</p>
                            @endif
                        </div>

                        <div>
                            <p class="text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Fingerprint</p>
                            <p class="mt-2 break-all rounded-2xl bg-gray-950 px-3 py-3 font-mono text-xs leading-6 text-gray-100 dark:bg-white/5">{{ $record->raw_fingerprint }}</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <p class="text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Tags</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @forelse ($tags as $tag)
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700 dark:bg-white/10 dark:text-slate-200">{{ $tag }}</span>
                                @empty
                                    <span class="text-sm text-gray-500 dark:text-gray-400">n/a</span>
                                @endforelse
                            </div>
                        </div>

                        <div>
                            <p class="text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Important</p>
                            <div class="mt-2">
                                <x-filament::badge :color="$record->is_important ? 'warning' : 'gray'">
                                    {{ $record->is_important ? 'Yes' : 'No' }}
                                </x-filament::badge>
                            </div>
                        </div>

                        <div>
                            <p class="text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Moderation Reason</p>
                            <p class="mt-2 text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $record->moderation_reason ?? 'n/a' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section heading="Content Preview" description="Текстовые поля, сохранённые в каталоге.">
            <div class="space-y-4">
                <div>
                    <p class="text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Original Content</p>
                    @if ($hasOriginalContent)
                        <div class="mt-2 max-h-80 overflow-auto whitespace-pre-wrap rounded-2xl bg-slate-50 px-4 py-4 text-sm leading-6 text-gray-800 dark:bg-white/5 dark:text-gray-100">{{ $record->content_original }}</div>
                    @else
                        <div class="mt-2 rounded-2xl border border-dashed border-gray-200 px-4 py-4 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            Исходный текст для этой записи не сохранён.
                        </div>
                    @endif
                </div>

                <div>
                    <p class="text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Translated Content</p>
                    @if ($hasTranslatedContent)
                        <div class="mt-2 max-h-80 overflow-auto whitespace-pre-wrap rounded-2xl bg-slate-50 px-4 py-4 text-sm leading-6 text-gray-800 dark:bg-white/5 dark:text-gray-100">{{ $record->content_translated }}</div>
                    @else
                        <div class="mt-2 rounded-2xl border border-dashed border-gray-200 px-4 py-4 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            Перевод и AI-обогащение для этой записи пока отсутствуют.
                        </div>
                    @endif
                </div>
            </div>
        </x-filament::section>
    </div>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
        <x-filament::section heading="Analysis Runtime" description="Состояние последней попытки AI-анализа и обогащения.">
            @if ($hasRuntimeTrace)
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-2xl bg-slate-50 px-4 py-4 dark:bg-white/5">
                        <p class="text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Status</p>
                        <div class="mt-3">
                            <x-filament::badge :color="$analysisRuntimeColor">
                                {{ str($analysisRuntimeStatus)->replace('_', ' ')->headline() }}
                            </x-filament::badge>
                        </div>
                    </div>
                    <div class="rounded-2xl bg-slate-50 px-4 py-4 dark:bg-white/5">
                        <p class="text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Attempt</p>
                        <p class="mt-2 text-sm font-semibold text-gray-950 dark:text-white">{{ $analysisRuntime['attempt'] ?? 'n/a' }}</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 px-4 py-4 dark:bg-white/5">
                        <p class="text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Queued At</p>
                        <p class="mt-2 text-sm font-semibold text-gray-950 dark:text-white">{{ $analysisRuntime['queued_at'] ?? 'n/a' }}</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 px-4 py-4 dark:bg-white/5">
                        <p class="text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Started At</p>
                        <p class="mt-2 text-sm font-semibold text-gray-950 dark:text-white">{{ $analysisRuntime['started_at'] ?? 'n/a' }}</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 px-4 py-4 dark:bg-white/5">
                        <p class="text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Finished At</p>
                        <p class="mt-2 text-sm font-semibold text-gray-950 dark:text-white">{{ $analysisRuntime['finished_at'] ?? 'n/a' }}</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 px-4 py-4 dark:bg-white/5">
                        <p class="text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Provider / Model</p>
                        <p class="mt-2 text-sm font-semibold text-gray-950 dark:text-white">
                            {{ $analysisRuntime['provider'] ?? 'n/a' }}
                            @if (filled($analysisRuntime['model'] ?? null))
                                · {{ $analysisRuntime['model'] }}
                            @endif
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Reasoning: {{ $analysisRuntime['reasoning_effort'] ?? 'n/a' }}
                        </p>
                    </div>
                </div>

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div class="rounded-2xl border border-dashed border-gray-200 px-4 py-4 dark:border-gray-700">
                        <p class="text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Fallback Reason</p>
                        <p class="mt-2 text-sm text-gray-700 dark:text-gray-200">{{ $analysisRuntime['fallback_reason'] ?? 'n/a' }}</p>
                    </div>
                    <div class="rounded-2xl border border-dashed border-gray-200 px-4 py-4 dark:border-gray-700">
                        <p class="text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Last Error</p>
                        <p class="mt-2 text-sm text-gray-700 dark:text-gray-200">{{ $analysisRuntime['last_error'] ?? 'n/a' }}</p>
                    </div>
                </div>
            @else
                <div class="rounded-2xl border border-dashed border-gray-200 px-4 py-4 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    Анализ ещё не запускался для сохранённой записи.
                </div>
            @endif
        </x-filament::section>

        <x-filament::section heading="Analysis Timeline" description="Шаги последней попытки обработки.">
            @if ($analysisTimeline !== [])
                <div class="space-y-3">
                    @foreach ($analysisTimeline as $event)
                        @php
                            $eventStatus = is_string($event['status'] ?? null) ? $event['status'] : 'info';
                            $eventClasses = match ($eventStatus) {
                                'completed' => 'border-emerald-200 bg-emerald-50 dark:border-emerald-400/20 dark:bg-emerald-400/10',
                                'failed' => 'border-rose-200 bg-rose-50 dark:border-rose-400/20 dark:bg-rose-400/10',
                                'fallback', 'queued' => 'border-amber-200 bg-amber-50 dark:border-amber-400/20 dark:bg-amber-400/10',
                                default => 'border-sky-200 bg-sky-50 dark:border-sky-400/20 dark:bg-sky-400/10',
                            };
                        @endphp

                        <div class="rounded-2xl border px-4 py-4 {{ $eventClasses }}">
                            <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $event['label'] ?? 'Event' }}</p>
                                    @if (filled($event['message'] ?? null))
                                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $event['message'] }}</p>
                                    @endif
                                </div>

                                <div class="text-xs uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">
                                    {{ $event['status'] ?? 'n/a' }} · {{ $event['at'] ?? 'n/a' }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="rounded-2xl border border-dashed border-gray-200 px-4 py-4 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    Timeline появится после первой сохранённой попытки анализа.
                </div>
            @endif
        </x-filament::section>
    </div>

    <div class="grid gap-5 xl:grid-cols-4">
        <x-filament::section heading="Source Metadata" description="Сырым JSON из исходного парсинга." collapsible>
            <pre class="max-h-96 overflow-auto rounded-2xl bg-gray-950 px-4 py-4 text-xs leading-6 text-gray-100">{{ $sourceMetadataJson }}</pre>
        </x-filament::section>

        @if ($hasAnalysisMetadata)
            <x-filament::section heading="Analysis Metadata" description="Что сохранилось после AI-обогащения." collapsible collapsed>
                <pre class="max-h-96 overflow-auto rounded-2xl bg-gray-950 px-4 py-4 text-xs leading-6 text-gray-100">{{ $analysisMetadataJson }}</pre>
            </x-filament::section>
        @endif

        @if ($hasRuntimeTrace)
            <x-filament::section heading="Analysis Runtime JSON" description="Сырой runtime-trace последней попытки." collapsible collapsed>
                <pre class="max-h-96 overflow-auto rounded-2xl bg-gray-950 px-4 py-4 text-xs leading-6 text-gray-100">{{ $analysisRuntimeJson }}</pre>
            </x-filament::section>
        @endif

        @if ($hasMediaPayload)
            <x-filament::section heading="Media Payload" description="Связанные изображения и вложения." collapsible collapsed>
                <pre class="max-h-96 overflow-auto rounded-2xl bg-gray-950 px-4 py-4 text-xs leading-6 text-gray-100">{{ $mediaJson }}</pre>
            </x-filament::section>
        @endif
    </div>
</div>
