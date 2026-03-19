<?php

declare(strict_types=1);

namespace App\Filament\Resources\News\Tables;

use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Catalog\Infrastructure\Persistence\Models\NewsItem;
use Modules\Shared\Domain\Enum\NewsStatus;

final class NewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(25)
            ->defaultSort('published_at', 'desc')
            ->deferFilters(false)
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns([
                'md' => 2,
                'xl' => 5,
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('source'))
            ->columns([
                TextColumn::make('published_at')
                    ->label('Published')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('n/a'),
                TextColumn::make('source.name')
                    ->label('Source')
                    ->searchable()
                    ->placeholder('n/a'),
                TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state): string => self::statusColor($state)),
                IconColumn::make('is_important')
                    ->label('Important')
                    ->boolean(),
                TextColumn::make('title_original')
                    ->label('Original Title')
                    ->searchable()
                    ->limit(80)
                    ->tooltip(fn (NewsItem $record): string => $record->title_original)
                    ->wrap(),
                TextColumn::make('title_generated')
                    ->label('Generated Title')
                    ->placeholder('n/a')
                    ->limit(80)
                    ->tooltip(fn (NewsItem $record): ?string => $record->title_generated)
                    ->wrap(),
                TextColumn::make('sentiment_score')
                    ->label('Sentiment')
                    ->badge()
                    ->sortable()
                    ->color(fn (int|string|null $state): string => self::sentimentColor((int) ($state ?? 0))),
                TextColumn::make('tags')
                    ->label('Tags')
                    ->state(fn (NewsItem $record): array => self::normalizeList($record->tags))
                    ->badge()
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->placeholder('n/a'),
                TextColumn::make('source_language')
                    ->label('Lang')
                    ->state(fn (NewsItem $record): ?string => self::stringMetadata($record, 'language'))
                    ->badge()
                    ->color('gray')
                    ->placeholder('n/a'),
                TextColumn::make('ai_analysis')
                    ->label('AI Analysis')
                    ->state(fn (NewsItem $record): string => self::analysisSummary($record))
                    ->badge()
                    ->color(fn (NewsItem $record): string => self::hasAiAnalysis($record) ? 'success' : 'gray')
                    ->tooltip(fn (NewsItem $record): string => self::analysisTooltip($record))
                    ->wrap(),
                TextColumn::make('analysis_provider')
                    ->label('AI Provider')
                    ->state(fn (NewsItem $record): ?string => self::stringMetadata($record, 'analysis', 'provider'))
                    ->badge()
                    ->color('info')
                    ->placeholder('n/a'),
                TextColumn::make('analysis_model')
                    ->label('AI Model')
                    ->state(fn (NewsItem $record): ?string => self::stringMetadata($record, 'analysis', 'model'))
                    ->placeholder('n/a'),
                TextColumn::make('analysis_reasoning')
                    ->label('Reasoning')
                    ->state(fn (NewsItem $record): ?string => self::stringMetadata($record, 'analysis', 'reasoning_effort'))
                    ->placeholder('n/a')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('media_count')
                    ->label('Media')
                    ->state(fn (NewsItem $record): int => count(self::normalizeList($record->media)))
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('raw_fingerprint')
                    ->label('Fingerprint')
                    ->copyable()
                    ->copyMessage('Fingerprint copied')
                    ->copyMessageDuration(1500)
                    ->limit(18)
                    ->tooltip(fn (NewsItem $record): string => $record->raw_fingerprint)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        NewsStatus::PROCESSING->value => 'Processing',
                        NewsStatus::PUBLISHED->value => 'Published',
                        NewsStatus::REJECTED->value => 'Rejected',
                    ]),
                SelectFilter::make('source')
                    ->relationship('source', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_important')
                    ->label('Important'),
                TernaryFilter::make('has_generated_title')
                    ->label('Generated Title')
                    ->queries(
                        true: function (Builder $query): Builder {
                            $query->whereNotNull('title_generated');

                            return $query;
                        },
                        false: function (Builder $query): Builder {
                            $query->whereNull('title_generated');

                            return $query;
                        },
                    ),
                TernaryFilter::make('has_ai_analysis')
                    ->label('AI Analysis')
                    ->queries(
                        true: function (Builder $query): Builder {
                            $query->whereNotNull('source_metadata->analysis->provider');

                            return $query;
                        },
                        false: function (Builder $query): Builder {
                            $query->whereNull('source_metadata->analysis->provider');

                            return $query;
                        },
                    ),
            ])
            ->actions([
                Action::make('details')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->slideOver()
                    ->modalWidth('7xl')
                    ->modalHeading(fn (NewsItem $record): string => str($record->title_generated ?: $record->title_original)->limit(110)->toString())
                    ->modalDescription(fn (NewsItem $record): string => sprintf(
                        'Source: %s%s',
                        $record->source->name,
                        $record->published_at !== null ? ' · Published: '.$record->published_at->setTimezone((string) config('app.timezone', 'UTC'))->toDateTimeString() : '',
                    ))
                    ->modalSubmitAction(false)
                    ->modalContent(fn (NewsItem $record) => view('filament.resources.news.details', [
                        'record' => $record,
                        'sourceMetadataJson' => self::prettyJson($record->source_metadata),
                        'analysisMetadataJson' => self::prettyJson(data_get($record->source_metadata, 'analysis')),
                        'mediaJson' => self::prettyJson($record->media),
                        'sourceLink' => self::stringMetadata($record, 'link'),
                        'sourceLanguage' => self::stringMetadata($record, 'language'),
                    ])),
                Action::make('open_source')
                    ->label('Open Source')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (NewsItem $record): ?string => self::stringMetadata($record, 'link'))
                    ->openUrlInNewTab()
                    ->visible(fn (NewsItem $record): bool => filled(self::stringMetadata($record, 'link'))),
            ]);
    }

    private static function statusColor(string $status): string
    {
        return match ($status) {
            NewsStatus::PUBLISHED->value => 'success',
            NewsStatus::REJECTED->value => 'danger',
            default => 'warning',
        };
    }

    private static function sentimentColor(int $score): string
    {
        return match (true) {
            $score >= 4 => 'success',
            $score <= -4 => 'danger',
            default => 'gray',
        };
    }

    /**
     * @return list<mixed>
     */
    private static function normalizeList(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    private static function stringMetadata(NewsItem $record, string ...$path): ?string
    {
        $value = data_get($record->source_metadata, implode('.', $path));

        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function prettyJson(mixed $value): string
    {
        $encoded = json_encode(
            $value,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        return is_string($encoded) ? $encoded : 'null';
    }

    private static function hasAiAnalysis(NewsItem $record): bool
    {
        return filled(self::stringMetadata($record, 'analysis', 'provider'));
    }

    private static function analysisSummary(NewsItem $record): string
    {
        $provider = self::stringMetadata($record, 'analysis', 'provider');
        $model = self::stringMetadata($record, 'analysis', 'model');

        if (! $provider) {
            return 'Not analyzed';
        }

        return $model ? sprintf('%s · %s', $provider, $model) : $provider;
    }

    private static function analysisTooltip(NewsItem $record): string
    {
        if (! self::hasAiAnalysis($record)) {
            return 'Для этой новости AI metadata пока не сохранены.';
        }

        $parts = array_filter([
            'Provider: '.self::stringMetadata($record, 'analysis', 'provider'),
            self::stringMetadata($record, 'analysis', 'model') !== null
                ? 'Model: '.self::stringMetadata($record, 'analysis', 'model')
                : null,
            self::stringMetadata($record, 'analysis', 'reasoning_effort') !== null
                ? 'Reasoning: '.self::stringMetadata($record, 'analysis', 'reasoning_effort')
                : null,
        ]);

        return implode(' | ', $parts);
    }
}
