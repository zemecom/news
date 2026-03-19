<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\QueueOverviewService;
use Filament\Widgets\Widget;

final class QueueOverviewWidget extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.queue-overview-widget';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'summaries' => app(QueueOverviewService::class)->getQueueSummaries(),
        ];
    }
}
