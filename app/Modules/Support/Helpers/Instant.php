<?php

declare(strict_types=1);

namespace App\Modules\Support\Helpers;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Catalog\Models\Currency\Quote;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use DateTimeZone;

class Instant
{
    /**
     * Resolve the currency's latest quote observed at or before
     * the given instant, via {@see Currency::quoteAsOf()}.
     */
    public static function resolveQuote(?Currency $currency, mixed $at): ?Quote
    {
        // If there is no currency, return null.
        if (! $currency instanceof Currency) {
            return null;
        }

        // If the instant is not a string or is an empty string, return null.
        if (! is_string($at) || $at === '') {
            return null;
        }

        // Parse the instant.
        try {
            $parsed = CarbonImmutable::parse($at);
        } catch (InvalidFormatException) {
            return null;
        }

        // Get the quote for the currency at the instant.
        return $currency->quoteAsOf($parsed);
    }

    /**
     * Convert a timezone-naive datetime-local value to UTC.
     */
    public static function toUtc(mixed $value, mixed $timezone): ?string
    {
        // If the value or timezone is not a string, return null.
        if (! is_string($value) || ! is_string($timezone)) {
            return null;
        }

        // Validate the timezone.
        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            return null;
        }

        // Validate the datetime-local format.
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?$/', $value) !== 1) {
            return null;
        }

        // Parse the value and convert to UTC.
        try {
            return CarbonImmutable::parse($value, $timezone)->utc()->toIso8601String();
        } catch (InvalidFormatException) {
            return null;
        }
    }
}
