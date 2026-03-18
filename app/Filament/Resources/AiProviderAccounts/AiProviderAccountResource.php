<?php

declare(strict_types=1);

namespace App\Filament\Resources\AiProviderAccounts;

use App\Filament\Resources\AiProviderAccounts\Pages\EditAiProviderAccount;
use App\Filament\Resources\AiProviderAccounts\Pages\ListAiProviderAccounts;
use App\Filament\Resources\AiProviderAccounts\Schemas\AiProviderAccountForm;
use App\Filament\Resources\AiProviderAccounts\Tables\AiProviderAccountsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Intelligence\Infrastructure\Persistence\Models\AiProviderAccount;
use Override;
use UnitEnum;

class AiProviderAccountResource extends Resource
{
    protected static ?string $model = AiProviderAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'AI Providers';

    protected static UnitEnum|string|null $navigationGroup = 'AI';

    protected static ?int $navigationSort = 10;

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return AiProviderAccountForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return AiProviderAccountsTable::configure($table);
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListAiProviderAccounts::route('/'),
            'edit' => EditAiProviderAccount::route('/{record}/edit'),
        ];
    }

    #[Override]
    public static function canCreate(): bool
    {
        return false;
    }
}
