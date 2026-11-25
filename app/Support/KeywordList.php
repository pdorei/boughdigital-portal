<?php

namespace App\Support;

class KeywordList
{
    /**
     * Trims values, drops blanks and removes case-insensitive duplicates while
     * keeping the original order. An empty list becomes null.
     */
    public static function clean(?array $values): ?array
    {
        $values = collect($values ?? [])
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn (string $value) => $value !== '')
            ->unique(fn (string $value) => mb_strtolower($value))
            ->values()
            ->all();

        return $values === [] ? null : $values;
    }
}
