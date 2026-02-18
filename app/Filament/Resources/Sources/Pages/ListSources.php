<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sources\Pages;

use App\Filament\Resources\Sources\SourceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Override;

class ListSources extends ListRecords
{
    protected static string $resource = SourceResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('parse_all')
                ->label('Run Crawler (All)')
                ->icon('heroicon-o-play')
                ->color('success')
                ->modalContent(view('filament.components.crawler-modal', ['sourceId' => null]))
                ->modalSubmitAction(false)
                ->modalCancelAction(false)
                ->modalWidth('xl'),
            CreateAction::make(),
        ];
    }

    #[Override]
    public function getMaxContentWidth(): \Filament\Support\Enums\Width|string|null
    {
        return \Filament\Support\Enums\Width::Full;
    }
}
