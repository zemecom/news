<?php

declare(strict_types=1);

namespace App\Filament\Resources\News\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Catalog\Infrastructure\Persistence\Models\NewsItem;
use Modules\Intelligence\Application\Services\EnqueueNewsAnalysisAction;
use Modules\Shared\Domain\Enum\NewsStatus;

final class NewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 15, 25, 50, 100])
            ->defaultSort('published_at', 'desc')
            ->deferFilters(false)
            ->extremePaginationLinks()
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->filtersLayout(FiltersLayout::Hidden)
            ->filtersFormWidth(Width::Full)
            ->filtersFormColumns([
                'md' => 3,
                'xl' => 6,
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('source'))
            ->columns([
                TextColumn::make('published_at')
                    ->label('Published')
                    ->dateTime()
                    ->sortable()
                    ->width('9rem')
                    ->placeholder('n/a'),
                TextColumn::make('source.name')
                    ->label('Source')
                    ->searchable()
                    ->width('7.5rem')
                    ->placeholder('n/a'),
                TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->width('6rem')
                    ->color(fn (string $state): string => self::statusColor($state)),
                IconColumn::make('is_important')
                    ->label('Important')
                    ->width('4.5rem')
                    ->boolean(),
                TextColumn::make('title_original')
                    ->label('Original Title')
                    ->searchable()
                    ->width('22rem')
                    ->grow()
                    ->limit(80)
                    ->lineClamp(2)
                    ->tooltip(fn (NewsItem $record): string => $record->title_original)
                    ->wrap(),
                TextColumn::make('title_generated')
                    ->label('Generated Title')
                    ->placeholder('n/a')
                    ->width('22rem')
                    ->grow()
                    ->limit(80)
                    ->lineClamp(2)
                    ->tooltip(fn (NewsItem $record): ?string => $record->title_generated)
                    ->wrap(),
                TextColumn::make('sentiment_score')
                    ->label('Sentiment')
                    ->badge()
                    ->sortable()
                    ->width('4.5rem')
                    ->color(fn (int|string|null $state): string => self::sentimentColor((int) ($state ?? 0))),
                TextColumn::make('tags')
                    ->label('Tags')
                    ->state(fn (NewsItem $record): array => self::normalizeList($record->tags))
                    ->badge()
                    ->color('warning')
                    ->width('12rem')
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->placeholder('n/a'),
                TextColumn::make('source_language')
                    ->label('Lang')
                    ->state(fn (NewsItem $record): ?string => self::stringMetadata($record, 'language'))
                    ->badge()
                    ->color('gray')
                    ->width('4rem')
                    ->placeholder('n/a'),
                TextColumn::make('analysis_status')
                    ->label('Analysis Status')
                    ->state(fn (NewsItem $record): string => self::analysisStatusLabel($record))
                    ->badge()
                    ->width('7rem')
                    ->wrapHeader()
                    ->color(fn (NewsItem $record): string => self::analysisStatusColor(self::analysisStatus($record)))
                    ->tooltip(fn (NewsItem $record): string => self::analysisStatusTooltip($record)),
                TextColumn::make('ai_analysis')
                    ->label('AI Analysis')
                    ->state(fn (NewsItem $record): string => self::analysisSummary($record))
                    ->badge()
                    ->width('10rem')
                    ->wrapHeader()
                    ->color(fn (NewsItem $record): string => self::hasAiAnalysis($record) ? 'success' : 'gray')
                    ->tooltip(fn (NewsItem $record): string => self::analysisTooltip($record))
                    ->lineClamp(2)
                    ->wrap(),
                TextColumn::make('ai_analysis_action')
                    ->label('AI Action')
                    ->state(fn (NewsItem $record): string => self::analysisActionLabel($record))
                    ->badge()
                    ->width('8.5rem')
                    ->wrapHeader()
                    ->icon(fn (NewsItem $record): string => self::hasAiAnalysis($record) ? 'heroicon-m-arrow-path' : 'heroicon-m-sparkles')
                    ->iconPosition(IconPosition::Before)
                    ->color(fn (NewsItem $record): string => self::analysisActionColor($record))
                    ->tooltip(fn (NewsItem $record): string => self::analysisActionTooltip($record))
                    ->weight(FontWeight::SemiBold)
                    ->extraAttributes(['class' => 'fi-news-ai-action'])
                    ->action(function (NewsItem $record): void {
                        self::queueAnalysis($record);
                    }),
                TextColumn::make('analysis_provider')
                    ->label('AI Provider')
                    ->state(fn (NewsItem $record): ?string => self::stringMetadata($record, 'analysis', 'provider'))
                    ->badge()
                    ->color('info')
                    ->width('7rem')
                    ->wrapHeader()
                    ->placeholder('n/a'),
                TextColumn::make('analysis_model')
                    ->label('AI Model')
                    ->state(fn (NewsItem $record): ?string => self::stringMetadata($record, 'analysis', 'model'))
                    ->width('6rem')
                    ->wrapHeader()
                    ->placeholder('n/a'),
                TextColumn::make('analysis_reasoning')
                    ->label('Reasoning')
                    ->state(fn (NewsItem $record): ?string => self::stringMetadata($record, 'analysis', 'reasoning_effort'))
                    ->width('5rem')
                    ->placeholder('n/a')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('media_count')
                    ->label('Media')
                    ->state(fn (NewsItem $record): int => count(self::normalizeList($record->media)))
                    ->badge()
                    ->width('4rem')
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
                SelectFilter::make('analysis_status')
                    ->label('Analysis Status')
                    ->options([
                        'not_analyzed' => 'Not Analyzed',
                        'queued' => 'Queued',
                        'running' => 'Running',
                        'completed' => 'Completed',
                        'fallback' => 'Fallback',
                        'failed' => 'Failed',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (! is_string($value) || $value === '') {
                            return $query;
                        }

                        if ($value === 'not_analyzed') {
                            $query->whereNull('source_metadata->analysis_runtime->status');

                            return $query;
                        }

                        $query->where('source_metadata->analysis_runtime->status', $value);

                        return $query;
                    }),
            ])
            ->actions([
                Action::make('details')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->slideOver()
                    ->modalWidth('7xl')
                    ->modalHeading(fn (NewsItem $record): string => str($record->title_generated ?: $record->title_original)->limit(110)->toString())
                    ->modalDescription(function (NewsItem $record): string {
                        $sourceName = $record->source instanceof \Modules\Catalog\Infrastructure\Persistence\Models\Source
                            ? $record->source->name
                            : 'n/a';

                        return sprintf(
                            'Source: %s%s',
                            $sourceName,
                            $record->published_at !== null ? ' · Published: '.$record->published_at->setTimezone((string) config('app.timezone', 'UTC'))->toDateTimeString() : '',
                        );
                    })
                    ->modalSubmitAction(false)
                    ->modalContent(fn (NewsItem $record) => view('filament.resources.news.details', [
                        'record' => $record,
                        'sourceMetadataJson' => self::prettyJson($record->source_metadata),
                        'analysisMetadataJson' => self::prettyJson(data_get($record->source_metadata, 'analysis')),
                        'analysisRuntimeJson' => self::prettyJson(data_get($record->source_metadata, 'analysis_runtime')),
                        'analysisRuntime' => self::analysisRuntime($record),
                        'analysisTimeline' => self::analysisTimeline($record),
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
            ])
            ->bulkActions([
                BulkAction::make('reanalyze_selected')
                    ->label('Reanalyze selected')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): void {
                        $count = app(EnqueueNewsAnalysisAction::class)
                            ->enqueueMany($records->modelKeys());

                        Notification::make()
                            ->title($count > 0 ? 'Selected news queued' : 'No news queued')
                            ->body($count > 0
                                ? sprintf('%d новостей поставлено в intelligence_tasks.', $count)
                                : 'Для выбранных записей не удалось запустить повторный анализ.')
                            ->color($count > 0 ? 'success' : 'warning')
                            ->send();
                    }),
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

    /**
     * @return array<string, mixed>
     */
    private static function analysisRuntime(NewsItem $record): array
    {
        $runtime = data_get($record->source_metadata, 'analysis_runtime');

        return is_array($runtime) ? $runtime : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function analysisTimeline(NewsItem $record): array
    {
        $timeline = data_get(self::analysisRuntime($record), 'timeline');

        return is_array($timeline) ? array_values(array_filter($timeline, 'is_array')) : [];
    }

    private static function analysisStatus(NewsItem $record): string
    {
        $status = data_get(self::analysisRuntime($record), 'status');

        return is_string($status) && $status !== '' ? $status : 'not_analyzed';
    }

    private static function analysisStatusLabel(NewsItem $record): string
    {
        return str(self::analysisStatus($record))
            ->replace('_', ' ')
            ->headline()
            ->toString();
    }

    private static function analysisStatusColor(string $status): string
    {
        return match ($status) {
            'completed' => 'success',
            'running' => 'info',
            'queued' => 'warning',
            'fallback' => 'warning',
            'failed' => 'danger',
            default => 'gray',
        };
    }

    private static function analysisStatusTooltip(NewsItem $record): string
    {
        $runtime = self::analysisRuntime($record);
        $parts = array_filter([
            data_get($runtime, 'provider') !== null ? 'Provider: '.data_get($runtime, 'provider') : null,
            data_get($runtime, 'model') !== null ? 'Model: '.data_get($runtime, 'model') : null,
            data_get($runtime, 'fallback_reason') !== null
                ? 'Fallback: '.str((string) data_get($runtime, 'fallback_reason'))->replace('_', ' ')->headline()->toString()
                : null,
            data_get($runtime, 'last_error') !== null ? 'Error: '.data_get($runtime, 'last_error') : null,
        ]);

        return $parts !== [] ? implode(' | ', $parts) : 'Анализ для этой записи ещё не запускался.';
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

    private static function analysisActionLabel(NewsItem $record): string
    {
        return self::hasAiAnalysis($record) ? 'Analyze Again' : 'Analyze';
    }

    private static function analysisActionColor(NewsItem $record): string
    {
        return self::hasAiAnalysis($record) ? 'warning' : 'info';
    }

    private static function analysisActionTooltip(NewsItem $record): string
    {
        return self::hasAiAnalysis($record)
            ? 'Поставить новость в очередь на повторный AI-анализ.'
            : 'Поставить новость в очередь на AI-анализ.';
    }

    private static function queueAnalysis(NewsItem $record): void
    {
        $hasExistingAnalysis = self::hasAiAnalysis($record);
        $queued = app(EnqueueNewsAnalysisAction::class)->enqueue((int) $record->getKey());

        Notification::make()
            ->title($queued
                ? ($hasExistingAnalysis ? 'Reanalysis queued' : 'Analysis queued')
                : 'Unable to queue news item')
            ->body($queued
                ? ($hasExistingAnalysis
                    ? 'Повторный AI-анализ поставлен в очередь intelligence_tasks.'
                    : 'AI-анализ поставлен в очередь intelligence_tasks.')
                : 'Не удалось реконструировать исходную запись для запуска AI-анализа.')
            ->color($queued ? 'success' : 'warning')
            ->send();
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
