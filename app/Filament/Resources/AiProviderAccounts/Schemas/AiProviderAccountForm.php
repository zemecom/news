<?php

declare(strict_types=1);

namespace App\Filament\Resources\AiProviderAccounts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Modules\Intelligence\Infrastructure\Persistence\Models\AiProviderAccount;

final class AiProviderAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('display_name')
                    ->required(),
                Toggle::make('is_enabled')
                    ->required(),
                Select::make('default_model')
                    ->label('Default model')
                    ->options(fn (?AiProviderAccount $record): array => self::availableModelOptions($record))
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state, ?AiProviderAccount $record): void {
                        $selectedEffort = $get->string('default_reasoning_effort', isNullable: true);

                        if ($selectedEffort === null) {
                            return;
                        }

                        $supportedEfforts = array_keys(self::availableReasoningEffortOptions(
                            record: $record,
                            selectedModel: $state,
                        ));

                        if (! in_array($selectedEffort, $supportedEfforts, true)) {
                            $set('default_reasoning_effort', null);
                        }
                    })
                    ->searchable()
                    ->native(false)
                    ->required(),
                Select::make('default_reasoning_effort')
                    ->label('Reasoning effort')
                    ->options(fn (Get $get, ?AiProviderAccount $record): array => self::availableReasoningEffortOptions(
                        record: $record,
                        selectedModel: $get->string('default_model', isNullable: true),
                    ))
                    ->native(false)
                    ->searchable()
                    ->placeholder('Use model default')
                    ->dehydrateStateUsing(static fn ($state): ?string => is_string($state) && $state !== '' ? $state : null),
                TextInput::make('max_parallel_jobs')
                    ->numeric()
                    ->required()
                    ->minValue(1),
                TextInput::make('slug')
                    ->disabled(),
                TextInput::make('provider')
                    ->disabled(),
                TextInput::make('codex_home_subpath')
                    ->disabled(),
                TextInput::make('auth_status')
                    ->disabled(),
                TextInput::make('auth_mode')
                    ->disabled(),
                TextInput::make('login_id')
                    ->label('Auth code')
                    ->disabled(),
                TextInput::make('auth_url')
                    ->label('Auth URL')
                    ->disabled(),
                TextInput::make('account_email')
                    ->disabled(),
                TextInput::make('plan_type')
                    ->disabled(),
                TextInput::make('rate_limit_used_percent')
                    ->label('Used %')
                    ->formatStateUsing(fn (?AiProviderAccount $record): string => $record?->rateLimitUsedPercent() !== null ? $record->rateLimitUsedPercent().'%' : 'n/a')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('rate_limit_reset_at')
                    ->label('Reset At')
                    ->formatStateUsing(fn (?AiProviderAccount $record): string => $record?->rateLimitResetAt()?->setTimezone((string) config('app.timezone', 'UTC'))->toDateTimeString() ?? 'n/a')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('weekly_rate_limit_used_percent')
                    ->label('Week Used %')
                    ->formatStateUsing(fn (?AiProviderAccount $record): string => $record?->weeklyRateLimitUsedPercent() !== null ? $record->weeklyRateLimitUsedPercent().'%' : 'n/a')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('weekly_rate_limit_reset_at')
                    ->label('Week Reset At')
                    ->formatStateUsing(fn (?AiProviderAccount $record): string => $record?->weeklyRateLimitResetAt()?->setTimezone((string) config('app.timezone', 'UTC'))->toDateTimeString() ?? 'n/a')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('last_status_checked_at')
                    ->disabled(),
                TextInput::make('last_authenticated_at')
                    ->disabled(),
                Textarea::make('last_error_message')
                    ->rows(4)
                    ->disabled(),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function availableModelOptions(?AiProviderAccount $record): array
    {
        $allOptions = self::normalizedStringMap(config('intelligence.chatgpt_codex.available_models', []));
        $planScopedOptions = self::filterOptionsByPlan($allOptions, $record?->plan_type);
        $options = $planScopedOptions !== [] ? $planScopedOptions : $allOptions;

        $currentModel = $record?->default_model;

        if (is_string($currentModel) && $currentModel !== '' && ! array_key_exists($currentModel, $options)) {
            $options = [$currentModel => $currentModel.' (custom)'] + $options;
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private static function availableReasoningEffortOptions(?AiProviderAccount $record, ?string $selectedModel = null): array
    {
        $allOptions = self::normalizedStringMap(config('intelligence.chatgpt_codex.available_reasoning_efforts', []));
        $supportedKeys = self::supportedReasoningEffortKeysForModel($selectedModel ?? $record?->default_model);
        $options = self::filterOptionsByKeys($allOptions, $supportedKeys);

        if ($options === []) {
            $options = $allOptions;
        }

        $currentEffort = $record?->default_reasoning_effort;

        if (is_string($currentEffort) && $currentEffort !== '' && ! array_key_exists($currentEffort, $options)) {
            $options = [$currentEffort => $currentEffort.' (custom)'] + $options;
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private static function normalizedStringMap(mixed $configured): array
    {
        $options = [];

        if (! is_array($configured)) {
            return $options;
        }

        foreach ($configured as $key => $value) {
            if (is_string($key) && $key !== '' && is_string($value) && $value !== '') {
                $options[$key] = $value;

                continue;
            }

            if (is_string($value) && $value !== '') {
                $options[$value] = $value;
            }
        }

        return $options;
    }

    /**
     * @param  array<string, string>  $allOptions
     * @return array<string, string>
     */
    private static function filterOptionsByPlan(array $allOptions, ?string $planType): array
    {
        $configured = config('intelligence.chatgpt_codex.available_models_by_plan', []);

        if (! is_array($configured)) {
            return $allOptions;
        }

        $normalizedPlan = self::normalizedPlanType($planType);
        $modelIds = $configured[$normalizedPlan] ?? null;

        if (! is_array($modelIds)) {
            return $allOptions;
        }

        return self::filterOptionsByKeys($allOptions, $modelIds);
    }

    private static function normalizedPlanType(?string $planType): string
    {
        $normalized = strtolower(trim((string) $planType));

        return match ($normalized) {
            'plus' => 'plus',
            'pro' => 'pro',
            default => '',
        };
    }

    /**
     * @param  array<string, string>  $options
     * @param  array<int, mixed>  $keys
     * @return array<string, string>
     */
    private static function filterOptionsByKeys(array $options, array $keys): array
    {
        $filtered = [];

        foreach ($keys as $key) {
            if (! is_string($key) || ! array_key_exists($key, $options)) {
                continue;
            }

            $filtered[$key] = $options[$key];
        }

        return $filtered;
    }

    /**
     * @return array<int, string>
     */
    private static function supportedReasoningEffortKeysForModel(?string $model): array
    {
        $configured = config('intelligence.chatgpt_codex.available_reasoning_efforts_by_model', []);

        if (! is_array($configured) || ! is_string($model) || $model === '') {
            return [];
        }

        $supported = $configured[$model] ?? [];

        if (! is_array($supported)) {
            return [];
        }

        return array_values(array_filter($supported, static fn (mixed $item): bool => is_string($item) && $item !== ''));
    }
}
