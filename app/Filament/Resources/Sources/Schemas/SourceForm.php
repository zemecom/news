<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sources\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

final class SourceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('url')
                    ->url()
                    ->required(),
                TextInput::make('type')
                    ->required()
                    ->default('rss'),
                TextInput::make('language_default'),
                TextInput::make('cron_expression'),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('retry_backoff_state'),
                DateTimePicker::make('last_success_at'),
                DateTimePicker::make('last_error_at'),
                TextInput::make('error_streak')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
