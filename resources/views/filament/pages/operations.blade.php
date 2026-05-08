@php
    $queueSummaries = is_array($queueSummaries ?? null) ? $queueSummaries : [];
    $failedJobs = is_array($failedJobs ?? null) ? $failedJobs : [];
@endphp

<x-filament-panels::page>
    <div wire:poll.10s="refreshOperationsPage" class="space-y-6">
        <x-filament::section heading="Queues & Analysis Health" description="Высокоуровневый broker snapshot и AI-ready контекст для оперативной навигации.">
            @include('filament.operations.partials.queue-overview-content', ['summaries' => $queueSummaries])
        </x-filament::section>

        <x-filament::section heading="Workers" description="Активное управление runtime-воркерами вынесено на отдельную страницу.">
            <div class="rounded-2xl border border-amber-200/70 bg-amber-50/70 px-4 py-4 text-sm leading-6 text-slate-700 dark:border-amber-400/10 dark:bg-amber-400/[0.08] dark:text-slate-200">
                Для `start/stop/restart`, bounded drain, Docker Control и log tail используй отдельную страницу
                <a href="{{ url('/admin/workers') }}" class="font-semibold text-amber-700 underline decoration-amber-400/70 underline-offset-4 dark:text-amber-200">
                    Workers
                </a>.
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
    </div>
</x-filament-panels::page>
