<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\AiProviderAccounts\AiProviderAccountResource;
use Filament\Widgets\Widget;
use Modules\Intelligence\Infrastructure\Persistence\Models\AiProviderAccount;

final class AiProviderStatusWidget extends Widget
{
    protected string $view = 'filament.widgets.ai-provider-status-widget';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var AiProviderAccount|null $record */
        $record = AiProviderAccount::query()
            ->where('provider', AiProviderAccount::PROVIDER_CHATGPT_CODEX)
            ->where('is_enabled', true)
            ->orderBy('id')
            ->first();

        return [
            'record' => $record,
            'providerUrl' => AiProviderAccountResource::getUrl('index'),
        ];
    }
}
