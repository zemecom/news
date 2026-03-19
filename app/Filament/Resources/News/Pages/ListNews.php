<?php

declare(strict_types=1);

namespace App\Filament\Resources\News\Pages;

use App\Filament\Resources\News\NewsResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Override;

final class ListNews extends ListRecords
{
    protected static string $resource = NewsResource::class;

    #[Override]
    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }
}
