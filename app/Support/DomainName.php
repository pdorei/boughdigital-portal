<?php

namespace App\Support;

use Illuminate\Support\Str;

class DomainName
{
    private const PATTERN = '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{0,61}[a-z0-9]$/';

    /**
     * Reduces a domain or website address to its bare host, for example
     * "https://www.Example.com/services" becomes "example.com". A leading
     * "www." is dropped so both spellings count as the same site.
     * Returns null when no valid host can be found.
     */
    public static function normalize(?string $value): ?string
    {
        $value = Str::lower(trim((string) $value));

        if ($value === '') {
            return null;
        }

        if (! preg_match('#^[a-z][a-z0-9+.-]*://#', $value)) {
            $value = "http://{$value}";
        }

        $host = parse_url($value, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        if (preg_match('/[^\x20-\x7e]/', $host) && function_exists('idn_to_ascii')) {
            $host = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $host;
        }

        $host = Str::of($host)->rtrim('.')->chopStart('www.')->toString();

        return preg_match(self::PATTERN, $host) ? $host : null;
    }
}
