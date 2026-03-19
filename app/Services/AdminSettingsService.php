<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdminSetting;

final class AdminSettingsService
{
    public const string NEWS_AUTO_REFRESH_SESSION_KEY = 'admin.news.auto_refresh_selection';

    public const string NEWS_AUTO_REFRESH_DEFAULT = 'default';

    public const string NEWS_AUTO_REFRESH_OFF = 'off';

    private const int DEFAULT_NEWS_AUTO_REFRESH_SECONDS = 15;

    /**
     * @var array<int, string>
     */
    private const array NEWS_AUTO_REFRESH_INTERVALS = [
        1 => '1 sec',
        5 => '5 sec',
        10 => '10 sec',
        15 => '15 sec',
        30 => '30 sec',
        60 => '1 min',
        120 => '2 min',
    ];

    public function getRecord(): AdminSetting
    {
        /** @var AdminSetting $settings */
        $settings = AdminSetting::query()->firstOrCreate(
            ['id' => 1],
            [
                'news_auto_refresh_enabled' => false,
                'news_auto_refresh_interval_seconds' => self::DEFAULT_NEWS_AUTO_REFRESH_SECONDS,
            ],
        );

        return $settings;
    }

    /**
     * @return array<string, string>
     */
    public function newsAutoRefreshSelectionOptions(): array
    {
        $options = [
            self::NEWS_AUTO_REFRESH_DEFAULT => $this->newsAutoRefreshDefaultSelectionLabel(),
            self::NEWS_AUTO_REFRESH_OFF => 'Off',
        ];

        foreach ($this->newsAutoRefreshIntervalSecondsOptions() as $seconds => $label) {
            $options[sprintf('%ds', $seconds)] = $label;
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public function newsAutoRefreshIntervalSecondsOptions(): array
    {
        return self::NEWS_AUTO_REFRESH_INTERVALS;
    }

    public function normalizeNewsAutoRefreshSelection(mixed $selection): string
    {
        if (! is_string($selection) || $selection === '') {
            return self::NEWS_AUTO_REFRESH_DEFAULT;
        }

        if (in_array($selection, [self::NEWS_AUTO_REFRESH_DEFAULT, self::NEWS_AUTO_REFRESH_OFF], true)) {
            return $selection;
        }

        if (! preg_match('/^(?<seconds>\d+)s$/', $selection, $matches)) {
            return self::NEWS_AUTO_REFRESH_DEFAULT;
        }

        $seconds = $this->normalizeNewsAutoRefreshSeconds($matches['seconds']);

        return $seconds !== null
            ? sprintf('%ds', $seconds)
            : self::NEWS_AUTO_REFRESH_DEFAULT;
    }

    public function resolveNewsAutoRefreshInterval(mixed $selection): ?string
    {
        $normalized = $this->normalizeNewsAutoRefreshSelection($selection);

        if ($normalized === self::NEWS_AUTO_REFRESH_DEFAULT) {
            return $this->configuredNewsAutoRefreshInterval();
        }

        if ($normalized === self::NEWS_AUTO_REFRESH_OFF) {
            return null;
        }

        return $normalized;
    }

    public function newsAutoRefreshSelectionLabel(mixed $selection): string
    {
        $normalized = $this->normalizeNewsAutoRefreshSelection($selection);

        return match ($normalized) {
            self::NEWS_AUTO_REFRESH_DEFAULT => $this->newsAutoRefreshDefaultSelectionLabel(),
            self::NEWS_AUTO_REFRESH_OFF => 'Off',
            default => $this->intervalLabel($normalized),
        };
    }

    public function persistNewsAutoRefreshDefaults(bool $enabled, mixed $seconds): AdminSetting
    {
        $settings = $this->getRecord();
        $settings->fill([
            'news_auto_refresh_enabled' => $enabled,
            'news_auto_refresh_interval_seconds' => $this->normalizeNewsAutoRefreshSeconds($seconds)
                ?? self::DEFAULT_NEWS_AUTO_REFRESH_SECONDS,
        ]);
        $settings->save();

        return $settings->refresh();
    }

    public function configuredNewsAutoRefreshInterval(): ?string
    {
        $settings = $this->getRecord();

        if (! $settings->news_auto_refresh_enabled) {
            return null;
        }

        $seconds = $this->normalizeNewsAutoRefreshSeconds($settings->news_auto_refresh_interval_seconds)
            ?? self::DEFAULT_NEWS_AUTO_REFRESH_SECONDS;

        return sprintf('%ds', $seconds);
    }

    public function defaultNewsAutoRefreshSeconds(): int
    {
        return $this->normalizeNewsAutoRefreshSeconds($this->getRecord()->news_auto_refresh_interval_seconds)
            ?? self::DEFAULT_NEWS_AUTO_REFRESH_SECONDS;
    }

    private function newsAutoRefreshDefaultSelectionLabel(): string
    {
        $configured = $this->configuredNewsAutoRefreshInterval();

        return sprintf(
            'Default (%s)',
            $configured !== null ? $this->intervalLabel($configured) : 'Off',
        );
    }

    private function intervalLabel(string $interval): string
    {
        $seconds = $this->normalizeNewsAutoRefreshSeconds(rtrim($interval, 's'));

        return $seconds !== null
            ? (self::NEWS_AUTO_REFRESH_INTERVALS[$seconds] ?? $interval)
            : $interval;
    }

    private function normalizeNewsAutoRefreshSeconds(mixed $seconds): ?int
    {
        $normalized = is_numeric($seconds) ? (int) $seconds : null;

        return is_int($normalized) && array_key_exists($normalized, self::NEWS_AUTO_REFRESH_INTERVALS)
            ? $normalized
            : null;
    }
}
