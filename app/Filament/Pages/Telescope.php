<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Override;
use UnitEnum;

final class Telescope extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static ?string $navigationLabel = 'Telescope';

    protected static ?string $slug = 'telescope';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 21;

    protected static ?string $title = 'Telescope';

    protected string $view = 'filament.pages.telescope';

    #[Override]
    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    #[Override]
    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    #[Override]
    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function getExtraBodyAttributes(): array
    {
        return [
            'class' => 'fi-body-telescope',
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function getViewData(): array
    {
        return [
            'telescopeUrl' => url('/telescope/requests'),
        ];
    }
}
