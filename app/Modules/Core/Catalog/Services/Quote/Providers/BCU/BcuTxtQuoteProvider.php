<?php

declare(strict_types=1);

namespace App\Modules\Core\Catalog\Services\Quote\Providers\BCU;

use App\Modules\Core\Catalog\Services\Quote\Contracts\QuoteProvider;
use App\Modules\Core\Catalog\Services\Quote\Exceptions\QuoteProviderException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class BcuTxtQuoteProvider implements QuoteProvider
{
    /**
     * BCU FileHandler that serves the daily cotizaciones TXT.
     */
    public const string URL = 'https://www.bcu.gub.uy/_layouts/15/BCU.Cotizaciones/handler/FileHandler.ashx';

    /**
     * Create a new BCU TXT quote provider.
     */
    public function __construct(
        private readonly BcuTxtQuoteParser $parser
    ) {}

    /**
     * Fetch BCU quotes for scheduled currencies whose codes appear in the TXT.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Modules\Core\Catalog\Models\Currency>  $currencies
     * @return \Illuminate\Support\Collection<int, array{code: string, observed_at: \Carbon\CarbonImmutable, rate: string}>
     */
    public function quotesOn(CarbonImmutable $date, Collection $currencies): Collection
    {
        // Get the observed at date.
        $observedAt = $date;

        // Get the wanted currencies by code.
        $wanted = $currencies->keyBy('code');

        // Parse the quotes and intersect them with the wanted currencies.
        return $this->parser
            ->parse($this->download($observedAt))
            ->intersectByKeys($wanted)
            ->map(fn (string $rate, string $code): array => [
                'code' => $code,
                'observed_at' => $observedAt,
                'rate' => $rate,
            ])
            ->values();
    }

    /**
     * Download the BCU cotizaciones TXT for the given date.
     */
    private function download(CarbonImmutable $date): string
    {
        $payload = json_encode([
            'KeyValuePairs' => [
                'Fecha' => $date->format('d/m/Y'),
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $body = Http::connectTimeout(3)
            ->timeout(15)
            // *.bcu.gub.uy is issued by Abitab IDdigital and the server
            // omits the intermediate, so public CA stores cannot verify it.
            ->withoutVerifying()
            ->retry([100, 500, 1000], 0, fn (Throwable $exception): bool => $exception instanceof ConnectionException
                || ($exception instanceof RequestException
                    && ($exception->response->serverError() || $exception->response->status() === 429)))
            ->get(self::URL, [
                'op' => 'downloadcotizacionestxt',
                'KeyValuePairs' => $payload,
            ])
            ->throw()
            ->body();

        if (Str::contains($body, 'Servicio no disponible', ignoreCase: true)) {
            throw new QuoteProviderException('The BCU cotizaciones service is unavailable.');
        }

        return $body;
    }
}
