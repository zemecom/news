<?php

declare(strict_types=1);

namespace App\Filament\Resources\AiProviderAccounts\Pages;

use App\Filament\Resources\AiProviderAccounts\AiProviderAccountResource;
use App\Filament\Support\AiProviderStatsAutoRefresher;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Override;

final class EditAiProviderAccount extends EditRecord
{
    protected static string $resource = AiProviderAccountResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function beforeFill(): void
    {
        app(AiProviderStatsAutoRefresher::class)->scheduleRefreshIfStale();
    }

    #[Override]
    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }
}
