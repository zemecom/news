<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\AiProviderStatusWidget;
use App\Services\QueueOverviewService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Override;
use UnitEnum;

final class Operations extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $navigationLabel = 'Operations';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 20;

    protected static ?string $title = 'Operations';

    protected string $view = 'filament.pages.operations';

    #[Override]
    public function getMaxContentWidth(): Width
    {
        return Width::ScreenTwoExtraLarge;
    }

    /**
     * @return array<class-string<Widget>>
     */
    #[Override]
    protected function getHeaderWidgets(): array
    {
        return [
            AiProviderStatusWidget::class,
        ];
    }

    #[Override]
    public function getHeaderWidgetsColumns(): int
    {
        return 1;
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function getViewData(): array
    {
        $overview = app(QueueOverviewService::class);

        return [
            'queueSummaries' => $overview->getQueueSummaries(),
            'failedJobs' => $overview->getRecentFailedJobs(),
        ];
    }

    public function refreshOperationsPage(): void {}
}
