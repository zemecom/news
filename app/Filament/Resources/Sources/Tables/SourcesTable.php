<?php

namespace App\Filament\Resources\Sources\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SourcesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('url')
                    ->searchable()
                    ->url(fn ($record) => $record->url)
                    ->openUrlInNewTab(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'rss' => 'success',
                        'telegram' => 'info',
                        default => 'gray',
                    }),
                IconColumn::make('is_active')
                    ->boolean()
                    ->action(function (\Illuminate\Database\Eloquent\Model $record, $column) {
                        $name = $column->getName();
                        $record->update([$name => ! $record->$name]);
                    }),
                TextColumn::make('news_items_count')
                    ->counts('newsItems')
                    ->label('Articles'),
                TextColumn::make('latest_article_at')
                    ->label('Latest Article')
                    ->state(function ($record) {
                        return $record->newsItems()->max('published_at');
                    })
                    ->dateTime()
                    ->sortable(query: function ($query, string $direction) {
                        return $query->withMax('newsItems', 'published_at')->orderBy('news_items_max_published_at', $direction);
                    }),
                TextColumn::make('last_success_at')
                    ->label('Last Run')
                    ->dateTime()
                    ->sortable()
                    ->since(),
                TextColumn::make('error_streak')
                    ->numeric()
                    ->sortable()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success'),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                \Filament\Actions\Action::make('parse')
                    ->label('Run')
                    ->icon('heroicon-o-play')
                    ->modalContent(fn ($record) => view('filament.components.crawler-modal', ['sourceId' => $record->id]))
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false)
                    ->modalWidth('xl'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
