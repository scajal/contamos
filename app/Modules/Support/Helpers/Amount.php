<?php

declare(strict_types=1);

namespace App\Modules\Support\Helpers;

use App\Modules\Core\Catalog\Models\Currency;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use NumberFormatter;

class Amount
{
    /**
     * Format the provided amount with the given
     * locale and currency.
     *
     * @param  array<'locale'|'decimals', mixed>  $options
     */
    public function format(string $amount, Currency $currency, array $options = []): string
    {
        /** @var int $decimals */
        $decimals = $options['decimals'] ?? $currency->decimal_places;

        /** @var string $locale */
        $locale = $options['locale'] ?? App::getLocale();

        // Get the unsigned amount.
        $unsigned = Str::startsWith($amount, '-') ? Str::substr($amount, 1) : $amount;

        // Split the amount into integer and fraction parts.
        [$integer, $fraction] = array_pad(explode('.', $unsigned), 2, '');

        // Get the formatted integer part.
        $formatted = $this->parse($integer, $locale);

        // If there are no decimals, return the formatted integer part.
        $formatted = $decimals === 0
            ? $formatted
            : $formatted.$this->decimalSeparator($locale).str_pad(substr($fraction, 0, $decimals), $decimals, '0');

        return "{$currency->symbol} {$formatted}";
    }

    /**
     * Normalise a submitted amount to the dot-decimal notation the
     * `decimal` validation rule accepts.
     *
     * Spanish keyboards write the decimal separator as a comma, so
     * "1.234,50" and "350,50" reach the server in a notation Laravel
     * rejects. The locale's own separators are stripped first, then a
     * lone comma is read as a decimal point so a Spanish keyboard on
     * an English locale still records the amount the user typed.
     *
     * @param  array<'locale', mixed>  $options
     */
    public function normalize(mixed $value, array $options = []): mixed
    {
        // Only strings carry a separator; anything else is left alone.
        if (! is_string($value) || blank($value = trim($value))) {
            return $value;
        }

        $lastDot = strrpos($value, '.');
        $lastComma = strrpos($value, ',');

        // Without a separator there is nothing to read.
        if ($lastDot === false && $lastComma === false) {
            return $value;
        }

        // With both characters the last one is the decimal point and
        // the other groups the thousands.
        if ($lastDot !== false && $lastComma !== false) {
            return $lastComma > $lastDot
                ? str_replace(',', '.', str_replace('.', '', $value))
                : str_replace(',', '', $value);
        }

        $separator = $lastDot !== false ? '.' : ',';

        // Repeated, it can only be grouping the thousands.
        if (substr_count($value, $separator) > 1) {
            return str_replace($separator, '', $value);
        }

        /** @var string $locale */
        $locale = $options['locale'] ?? App::getLocale();

        // A lone comma before exactly three digits groups the thousands
        // on a locale that writes them that way. Everywhere else a lone
        // separator is the decimal point, which keeps the dot notation
        // the edit form is prefilled with intact.
        if ($separator === ',' && $this->groupingSeparator($locale) === ',' && preg_match('/,\d{3}$/', $value) === 1) {
            return str_replace(',', '', $value);
        }

        return str_replace(',', '.', $value);
    }

    /**
     * Format the provided string to the current
     * locale with the formatter.
     */
    protected function parse(string $amount, string $locale): string
    {
        if (blank($amount = ltrim($amount, '0'))) {
            $amount = '0';
        }

        // Get the group size.
        $groupSize = is_int($groupSize = ($formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL))->getAttribute(NumberFormatter::GROUPING_SIZE)) && $groupSize > 0
                ? $groupSize
                : 3;

        // Get the grouping separator.
        $groupingSeparator = $formatter->getSymbol(NumberFormatter::GROUPING_SEPARATOR_SYMBOL) ?: ',';

        return strrev(implode($groupingSeparator, str_split(strrev($amount), $groupSize)));
    }

    /**
     * Get the decimal separator.
     */
    protected function decimalSeparator(string $locale): string
    {
        /** @var string $separator */
        $separator = new NumberFormatter($locale, NumberFormatter::DECIMAL)
            ->getSymbol(NumberFormatter::DECIMAL_SEPARATOR_SYMBOL) ?: '.';

        return $separator;
    }

    /**
     * Get the grouping separator.
     */
    protected function groupingSeparator(string $locale): string
    {
        /** @var string $separator */
        $separator = new NumberFormatter($locale, NumberFormatter::DECIMAL)
            ->getSymbol(NumberFormatter::GROUPING_SEPARATOR_SYMBOL) ?: ',';

        return $separator;
    }
}
