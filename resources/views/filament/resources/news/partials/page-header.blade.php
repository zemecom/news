<header class="fi-header fi-news-page-header">
    <div class="fi-news-page-header-title">
        @if ($breadcrumbs)
            <x-filament::breadcrumbs :breadcrumbs="$breadcrumbs" />
        @endif

        @if (filled($heading))
            <h1 class="fi-header-heading">
                {{ $heading }}
            </h1>
        @endif

        @if (filled($subheading))
            <p class="fi-header-subheading">
                {{ $subheading }}
            </p>
        @endif
    </div>

    <div class="fi-news-page-header-filters">
        <div class="fi-news-page-header-filters-bar">
            <span class="fi-news-page-header-filters-label">
                Filters
            </span>

            <x-filament::link
                color="danger"
                tag="button"
                wire:click="resetTableFiltersForm"
            >
                Reset
            </x-filament::link>
        </div>

        <div class="fi-news-page-header-filters-form">
            {{ $tableFiltersForm }}
        </div>
    </div>

    @if ($headerActions)
        <div class="fi-news-page-header-actions">
            <x-filament::actions
                :actions="$headerActions"
                :alignment="$headerActionsAlignment"
            />
        </div>
    @endif
</header>
