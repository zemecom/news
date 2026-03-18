<?php

declare(strict_types=1);

namespace App\Filament\Resources\AiProviderAccounts\Tables;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Intelligence\Domain\Exceptions\AiProviderException;
use Modules\Intelligence\Infrastructure\Codex\CodexAccountStatusSynchronizer;
use Modules\Intelligence\Infrastructure\Codex\CodexLoginManager;
use Modules\Intelligence\Infrastructure\Persistence\Models\AiProviderAccount;
use Throwable;

final class AiProviderAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->poll('10s')
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('display_name')
                    ->label('Provider')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('auth_status')
                    ->label('Status')
                    ->state(fn (AiProviderAccount $record): string => $record->statusLabel())
                    ->badge()
                    ->color(fn (AiProviderAccount $record): string => $record->statusColor()),
                TextColumn::make('account_email')
                    ->label('Email')
                    ->placeholder('n/a'),
                TextColumn::make('plan_type')
                    ->label('Plan')
                    ->badge()
                    ->placeholder('n/a'),
                TextColumn::make('login_id')
                    ->label('Auth Code')
                    ->placeholder('n/a')
                    ->copyable(fn (?string $state): bool => filled($state))
                    ->copyableState(fn (?string $state): ?string => $state)
                    ->copyMessage('Код скопирован')
                    ->copyMessageDuration(1500),
                TextColumn::make('default_model')
                    ->label('Model'),
                TextColumn::make('default_reasoning_effort')
                    ->label('Reasoning')
                    ->formatStateUsing(static fn (?string $state): string => match ($state) {
                        null, '' => 'Model default',
                        'xhigh' => 'XHigh',
                        default => ucfirst($state),
                    }),
                TextColumn::make('max_parallel_jobs')
                    ->label('Slots')
                    ->numeric(),
                TextColumn::make('rate_limit_used')
                    ->label('Used %')
                    ->state(fn (AiProviderAccount $record): string => $record->rateLimitUsedPercent() !== null ? (string) $record->rateLimitUsedPercent().'%' : 'n/a'),
                TextColumn::make('rate_limit_reset_at')
                    ->label('Reset At')
                    ->state(fn (AiProviderAccount $record): ?string => $record->rateLimitResetAt()?->setTimezone((string) config('app.timezone', 'UTC'))->toDateTimeString())
                    ->placeholder('n/a'),
                TextColumn::make('rate_limit_weekly_used')
                    ->label('Week Used %')
                    ->state(fn (AiProviderAccount $record): string => $record->weeklyRateLimitUsedPercent() !== null ? (string) $record->weeklyRateLimitUsedPercent().'%' : 'n/a'),
                TextColumn::make('rate_limit_weekly_reset_at')
                    ->label('Week Reset At')
                    ->state(fn (AiProviderAccount $record): ?string => $record->weeklyRateLimitResetAt()?->setTimezone((string) config('app.timezone', 'UTC'))->toDateTimeString())
                    ->placeholder('n/a'),
                TextColumn::make('last_error_message')
                    ->label('Last Error')
                    ->limit(60)
                    ->tooltip(fn (AiProviderAccount $record): ?string => $record->last_error_message),
                TextColumn::make('last_status_checked_at')
                    ->label('Last Checked')
                    ->dateTime()
                    ->since()
                    ->placeholder('n/a'),
            ])
            ->actions([
                EditAction::make(),
                Action::make('authenticate')
                    ->label('Authenticate')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->visible(fn (AiProviderAccount $record): bool => ! $record->isAuthenticated())
                    ->action(function (AiProviderAccount $record): void {
                        try {
                            app(CodexLoginManager::class)->startLogin($record);
                        } catch (Throwable $e) {
                            self::notifyError($e);

                            return;
                        }

                        $record->refresh();

                        Notification::make()
                            ->title('Device auth started')
                            ->body(sprintf(
                                'Открой `Open Auth URL` и введи код `%s`, чтобы завершить вход в ChatGPT Codex.',
                                (string) ($record->login_id ?? 'n/a'),
                            ))
                            ->success()
                            ->send();
                    }),
                Action::make('open_auth_url')
                    ->label('Open Auth URL')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->visible(fn (AiProviderAccount $record): bool => ($record->auth_url ?? null) !== null)
                    ->url(fn (AiProviderAccount $record): ?string => $record->auth_url)
                    ->openUrlInNewTab(),
                Action::make('refresh_status')
                    ->label('Refresh Status')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (AiProviderAccount $record): void {
                        try {
                            app(CodexAccountStatusSynchronizer::class)->sync($record);
                        } catch (Throwable $e) {
                            self::notifyError($e);

                            return;
                        }

                        Notification::make()
                            ->title('Provider status refreshed')
                            ->success()
                            ->send();
                    }),
                Action::make('cancel_auth')
                    ->label('Cancel Auth')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->visible(fn (AiProviderAccount $record): bool => $record->isPending())
                    ->action(function (AiProviderAccount $record): void {
                        try {
                            app(CodexLoginManager::class)->cancelLogin($record);
                        } catch (Throwable $e) {
                            self::notifyError($e);

                            return;
                        }

                        Notification::make()
                            ->title('Pending auth canceled')
                            ->success()
                            ->send();
                    }),
                Action::make('logout')
                    ->label('Logout')
                    ->icon('heroicon-o-arrow-left-on-rectangle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (AiProviderAccount $record): bool => $record->isAuthenticated() || $record->auth_status === AiProviderAccount::STATUS_RATE_LIMITED)
                    ->action(function (AiProviderAccount $record): void {
                        try {
                            app(CodexLoginManager::class)->logout($record);
                        } catch (Throwable $e) {
                            self::notifyError($e);

                            return;
                        }

                        Notification::make()
                            ->title('Provider logged out')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    private static function notifyError(Throwable $e): void
    {
        if ($e instanceof AiProviderException) {
            $message = $e->getMessage();
        } else {
            $message = 'Unexpected provider error: '.$e->getMessage();
        }

        Notification::make()
            ->title('AI provider action failed')
            ->body($message)
            ->danger()
            ->send();
    }
}
