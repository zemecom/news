<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sources\Pages;

use App\Filament\Resources\Sources\SourceResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Override;

final class ListSources extends ListRecords
{
    protected static string $resource = SourceResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('parse_all')
                ->label('Run Crawler (All)')
                ->icon('heroicon-o-play')
                ->color('success')
                ->modalContent(view('filament.components.crawler-modal', ['sourceId' => null]))
                ->modalSubmitAction(false)
                ->modalCancelAction(false)
                ->modalWidth('7xl'),
            CreateAction::make(),
        ];
    }

    #[Override]
    public function getMaxContentWidth(): Width
    {
        return Width::ScreenTwoExtraLarge;
    }
}
