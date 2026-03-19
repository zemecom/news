<x-filament-widgets::widget wire:poll.10s class="fi-queue-overview-widget">
    <x-filament::section>
        @include('filament.operations.partials.queue-overview-content', ['summaries' => $summaries])
    </x-filament::section>
</x-filament-widgets::widget>
