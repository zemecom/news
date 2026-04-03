@php
    $telescopeUrl = is_string($telescopeUrl ?? null) ? $telescopeUrl : url('/telescope/requests');
@endphp

<div class="fi-telescope-page">
    <div class="fi-telescope-toolbar">
        <x-filament::button
            tag="a"
            :href="$telescopeUrl"
            target="_blank"
            rel="noopener noreferrer"
            icon="heroicon-o-arrow-top-right-on-square"
            color="gray"
            size="sm"
        >
            Open In New Tab
        </x-filament::button>
    </div>

    <div class="fi-telescope-frame">
        <iframe
            src="{{ $telescopeUrl }}"
            title="Laravel Telescope Requests"
            class="fi-telescope-iframe"
            loading="lazy"
        ></iframe>
    </div>
</div>
