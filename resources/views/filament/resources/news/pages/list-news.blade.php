@php
    $pollingInterval = $this->newsAutoRefreshInterval();
@endphp

<x-filament-panels::page>
    <div
        @if (filled($pollingInterval))
            wire:poll.{{ $pollingInterval }}="refreshNewsPage"
        @endif
    >
        {{ $this->content }}
    </div>
</x-filament-panels::page>
