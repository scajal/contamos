<?php

declare(strict_types=1);

namespace App\Modules\Core\Catalog\QuoteProviders;

use App\Modules\Core\Catalog\Exceptions\QuoteProviderException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BcuTxtQuoteParser
{
    /**
     * Marker that starts the international FX section.
     */
    private const string INTERNATIONAL_START = 'M E R C A D O    D E    C A M B I O S    I N T E R N A C I O N A L';

    /**
     * Marker that starts the local interbank section and ends the international section.
     */
    private const string LOCAL_START = 'C O T I Z A C I O N E S    I N T E R B A N C A R I A S';

    /**
     * Marker that ends the local interbank section.
     */
    private const string LOCAL_END = 'M E T A L E S    P R E C I O S O S';

    /**
     * BCU numeric codes in the local section mapped to \App\Modules\Core\Catalog\Models\Currency codes.
     *
     * @var array<string, string>
     */
    private const array LOCAL_CODES = [
        '2225' => 'UYU',
        '9700' => 'UP',
        '9800' => 'UI',
        '9900' => 'UR',
    ];

    /**
     * Parse a BCU cotizaciones TXT into rates keyed by currency code.
     *
     * International rows use ISO codes. A single asterisk is units per 1 USD;
     * a double asterisk is USD per unit and is inverted so every rate is 1 USD = X.
     * Local rows that are not already present are added from the BCU code map.
     *
     * @return \Illuminate\Support\Collection<string, string>
     */
    public function parse(string $contents): Collection
    {
        $quotes = $this->parseInternational($this->section($contents, self::INTERNATIONAL_START, self::LOCAL_START));

        foreach ($this->parseLocal($this->section($contents, self::LOCAL_START, self::LOCAL_END)) as $code => $rate) {
            if (! $quotes->has($code)) {
                $quotes->put($code, $rate);
            }
        }

        return $quotes;
    }

    /**
     * Extract the text between two section markers.
     */
    private function section(string $contents, string $startMarker, string $endMarker): string
    {
        $start = Str::position($contents, $startMarker);

        if ($start === false) {
            throw new QuoteProviderException("The BCU TXT is missing the [{$startMarker}] section.");
        }

        $from = $start + Str::length($startMarker);
        $end = Str::position($contents, $endMarker, $from);

        if ($end === false) {
            throw new QuoteProviderException("The BCU TXT is missing the [{$endMarker}] section.");
        }

        return Str::substr($contents, $from, $end - $from);
    }

    /**
     * Parse international FX rows into 1 USD = X rates keyed by ISO code.
     *
     * @return \Illuminate\Support\Collection<string, string>
     */
    private function parseInternational(string $section): Collection
    {
        $quotes = collect();

        foreach (Str::of($section)->explode("\n") as $line) {
            $line = Str::trim($line);

            if (! preg_match('/^([A-Z]{3})\s+.+\s+(\d{4})\s+(\*{1,2})\s+([\d.]+)\s*$/', $line, $matches)) {
                continue;
            }

            $code = $matches[1];
            $convention = $matches[3];
            $arbitraje = $matches[4];

            $quotes->put($code, $convention === '**' ? bcdiv('1', $arbitraje, 18) : $arbitraje);
        }

        return $quotes;
    }

    /**
     * Parse local interbank rows mapped from BCU numeric codes.
     *
     * @return \Illuminate\Support\Collection<string, string>
     */
    private function parseLocal(string $section): Collection
    {
        $quotes = collect();

        foreach (Str::of($section)->explode("\n") as $line) {
            $line = Str::trim($line);

            if (! preg_match('/\s(\d{4})\s+[A-Z]{2,3}\s+([\d.]+)\s*$/', $line, $matches)) {
                continue;
            }

            $code = self::LOCAL_CODES[$matches[1]] ?? null;

            if (! is_string($code)) {
                continue;
            }

            $quotes->put($code, $matches[2]);
        }

        return $quotes;
    }
}
