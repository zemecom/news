<?php

namespace App\Filament\Resources\Sources\Pages;

use App\Filament\Resources\Sources\SourceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSources extends ListRecords
{
    protected static string $resource = SourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('parse_all')
                ->label('Run Crawler (All)')
                ->icon('heroicon-o-play')
                ->color('success')
                ->modalContent(view('livewire.crawler-log'))
                ->modalSubmitAction(false)
                ->modalCancelAction(false)
                ->modalWidth('xl'),
            CreateAction::make(),
        ];
    }
}
