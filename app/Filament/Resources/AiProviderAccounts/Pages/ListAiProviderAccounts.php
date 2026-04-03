<?php

declare(strict_types=1);

namespace App\Filament\Resources\AiProviderAccounts\Pages;

use App\Filament\Resources\AiProviderAccounts\AiProviderAccountResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Modules\Intelligence\Application\Services\SyncAiProviderStatsAction;
use Modules\Intelligence\Infrastructure\Persistence\Models\AiProviderAccount;
use Override;
use Throwable;

final class ListAiProviderAccounts extends ListRecords
{
    protected static string $resource = AiProviderAccountResource::class;

    #[Override]
    public function mount(): void
    {
        app(\App\Filament\Support\AiProviderStatsAutoRefresher::class)->scheduleRefreshIfStale();

        parent::mount();
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh_statistics')
                ->label('Refresh Statistics')
                ->icon('heroicon-o-arrow-path')
                ->action(function (): void {
                    try {
                        $summary = app(SyncAiProviderStatsAction::class)->run(AiProviderAccount::PROVIDER_CHATGPT_CODEX);
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('AI statistics refresh failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    $title = $summary['failed'] > 0
                        ? 'AI statistics refreshed with warnings'
                        : 'AI statistics refreshed';

                    $notification = Notification::make()
                        ->title($title)
                        ->body(sprintf(
                            'Проверено %d провайдеров, обновлено %d, ошибок %d.',
                            $summary['checked'],
                            $summary['updated'],
                            $summary['failed'],
                        ));

                    if ($summary['failed'] > 0) {
                        $notification->warning()->send();

                        return;
                    }

                    $notification->success()->send();
                }),
        ];
    }

    #[Override]
    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }
}
