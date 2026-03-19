<?php

declare(strict_types=1);

namespace App\Filament\Resources\News;

use App\Filament\Resources\News\Pages\ListNews;
use App\Filament\Resources\News\Tables\NewsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Catalog\Infrastructure\Persistence\Models\NewsItem;
use Override;

final class NewsResource extends Resource
{
    protected static ?string $model = NewsItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?string $navigationLabel = 'News';

    protected static ?int $navigationSort = 5;

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return NewsTable::configure($table);
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListNews::route('/'),
        ];
    }

    #[Override]
    public static function canCreate(): bool
    {
        return false;
    }
}
