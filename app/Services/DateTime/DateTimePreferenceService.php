<?php

namespace App\Services\DateTime;

use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use DateTimeZone;

class DateTimePreferenceService
{
    public const DATE_FORMATS = [
        'd/m/Y' => '19/08/2026',
        'm/d/Y' => '08/19/2026',
        'Y-m-d' => '2026-08-19',
        'd M Y' => '19 Aug 2026',
        'M d, Y' => 'Aug 19, 2026',
    ];

    public const DATETIME_FORMATS = [
        'd/m/Y H:i' => '19/08/2026 14:30',
        'd/m/Y h:i A' => '19/08/2026 02:30 PM',
        'Y-m-d H:i' => '2026-08-19 14:30',
        'd M Y H:i' => '19 Aug 2026 14:30',
        'M d, Y h:i A' => 'Aug 19, 2026 02:30 PM',
    ];

    public function resolve(?User $user = null, ?Workspace $workspace = null): array
    {
        return [
            'timezone' => $this->resolvedSetting($user?->timezone, $this->workspaceSetting($workspace, 'timezone'), $this->defaultTimezone()),
            'date_format' => $this->resolvedSetting($user?->date_format, $this->workspaceSetting($workspace, 'date_format'), $this->defaultDateFormat()),
            'datetime_format' => $this->resolvedSetting($user?->datetime_format, $this->workspaceSetting($workspace, 'datetime_format'), $this->defaultDateTimeFormat()),
        ];
    }

    public function resolveWithSources(?User $user = null, ?Workspace $workspace = null): array
    {
        return [
            'timezone' => $this->resolvedSettingWithSource($user?->timezone, $this->workspaceSetting($workspace, 'timezone'), $this->defaultTimezone()),
            'date_format' => $this->resolvedSettingWithSource($user?->date_format, $this->workspaceSetting($workspace, 'date_format'), $this->defaultDateFormat()),
            'datetime_format' => $this->resolvedSettingWithSource($user?->datetime_format, $this->workspaceSetting($workspace, 'datetime_format'), $this->defaultDateTimeFormat()),
        ];
    }

    public function workspaceBusinessPreferences(?Workspace $workspace = null): array
    {
        return [
            'timezone' => $this->workspaceSetting($workspace, 'timezone') ?: $this->defaultTimezone(),
            'date_format' => $this->workspaceSetting($workspace, 'date_format') ?: $this->defaultDateFormat(),
            'datetime_format' => $this->workspaceSetting($workspace, 'datetime_format') ?: $this->defaultDateTimeFormat(),
        ];
    }

    public function workspaceRawPreferences(?Workspace $workspace = null): array
    {
        return [
            'timezone' => $this->workspaceSetting($workspace, 'timezone'),
            'date_format' => $this->workspaceSetting($workspace, 'date_format'),
            'datetime_format' => $this->workspaceSetting($workspace, 'datetime_format'),
        ];
    }

    public function userRawPreferences(?User $user = null): array
    {
        return [
            'timezone' => $user?->timezone,
            'date_format' => $user?->date_format,
            'datetime_format' => $user?->datetime_format,
        ];
    }

    public function formatDate(string|DateTimeInterface|null $value, ?User $user = null, ?Workspace $workspace = null, ?string $fallback = null): string
    {
        if (! $value) {
            return $fallback ?? '';
        }

        $preferences = $this->resolve($user, $workspace);

        return $this->asCarbon($value)->format($preferences['date_format']);
    }

    public function formatDateTime(string|DateTimeInterface|null $value, ?User $user = null, ?Workspace $workspace = null, ?string $fallback = null): string
    {
        if (! $value) {
            return $fallback ?? '';
        }

        $preferences = $this->resolve($user, $workspace);

        return $this->asCarbon($value)->timezone($preferences['timezone'])->format($preferences['datetime_format']);
    }

    public function formatTime(string|DateTimeInterface|null $value, ?User $user = null, ?Workspace $workspace = null, ?string $fallback = null): string
    {
        if (! $value) {
            return $fallback ?? '';
        }

        $preferences = $this->resolve($user, $workspace);

        return $this->asCarbon($value)->timezone($preferences['timezone'])->format('H:i');
    }

    public function options(?User $user = null, ?Workspace $workspace = null): array
    {
        $workspacePreferences = $this->workspaceBusinessPreferences($workspace);

        return [
            'timezone_options' => $this->timezoneOptions(),
            'date_format_options' => $this->formatOptions(self::DATE_FORMATS),
            'datetime_format_options' => $this->formatOptions(self::DATETIME_FORMATS),
            'user_timezone_options' => array_merge([
                ['value' => '', 'label' => "Use Workspace Default ({$workspacePreferences['timezone']})"],
            ], $this->timezoneOptions()),
            'user_date_format_options' => array_merge([
                ['value' => '', 'label' => 'Use Workspace Default ('.$this->exampleFor($workspacePreferences['date_format'], self::DATE_FORMATS).')'],
            ], $this->formatOptions(self::DATE_FORMATS)),
            'user_datetime_format_options' => array_merge([
                ['value' => '', 'label' => 'Use Workspace Default ('.$this->exampleFor($workspacePreferences['datetime_format'], self::DATETIME_FORMATS).')'],
            ], $this->formatOptions(self::DATETIME_FORMATS)),
        ];
    }

    public function defaultTimezone(): string
    {
        return config('date_preferences.timezone', 'Australia/Sydney');
    }

    public function defaultDateFormat(): string
    {
        return config('date_preferences.date_format', 'd/m/Y');
    }

    public function defaultDateTimeFormat(): string
    {
        return config('date_preferences.datetime_format', 'd/m/Y H:i');
    }

    private function resolvedSetting(?string $userValue, ?string $workspaceValue, string $default): string
    {
        return $userValue ?: ($workspaceValue ?: $default);
    }

    private function resolvedSettingWithSource(?string $userValue, ?string $workspaceValue, string $default): array
    {
        if ($userValue) {
            return ['value' => $userValue, 'source' => 'user'];
        }

        if ($workspaceValue) {
            return ['value' => $workspaceValue, 'source' => 'workspace'];
        }

        return ['value' => $default, 'source' => 'application'];
    }

    private function workspaceSetting(?Workspace $workspace, string $key): ?string
    {
        $value = $workspace?->settings[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function timezoneOptions(): array
    {
        $priorityTimezones = [
            'Australia/Sydney',
            'Australia/Melbourne',
            'Australia/Brisbane',
            'Australia/Perth',
            'Australia/Adelaide',
            'Asia/Phnom_Penh',
            'Asia/Singapore',
            'Asia/Bangkok',
            'Asia/Tokyo',
            'Europe/London',
            'America/New_York',
            'UTC',
        ];

        return collect($priorityTimezones)
            ->merge(DateTimeZone::listIdentifiers())
            ->unique()
            ->values()
            ->map(fn (string $identifier) => ['value' => $identifier, 'label' => $identifier])
            ->all();
    }

    private function formatOptions(array $formats): array
    {
        return collect($formats)
            ->map(fn (string $example, string $format) => ['value' => $format, 'label' => "{$example} ({$format})"])
            ->values()
            ->all();
    }

    private function exampleFor(string $format, array $formats): string
    {
        return $formats[$format] ?? $format;
    }

    private function asCarbon(string|DateTimeInterface $value): CarbonInterface
    {
        return $value instanceof DateTimeInterface ? Carbon::instance($value) : Carbon::parse($value);
    }
}
