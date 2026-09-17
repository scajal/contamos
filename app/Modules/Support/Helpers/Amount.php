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
}
