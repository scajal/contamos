<?php

declare(strict_types=1);

namespace App\Modules\Core\Catalog\QuoteProviders;

use App\Modules\Core\Catalog\Contracts\Currency\QuoteProvider;
use App\Modules\Core\Catalog\Exceptions\QuoteProviderException;
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

    public function __construct(private BcuTxtQuoteParser $parser) {}

    /**
     * Fetch BCU quotes for scheduled currencies whose codes appear in the TXT.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Modules\Core\Catalog\Models\Currency>  $currencies
     * @return \Illuminate\Support\Collection<int, array{code: string, observed_at: \Carbon\CarbonImmutable, rate: string}>
     */
    public function quotesOn(CarbonImmutable $date, Collection $currencies): Collection
    {
        $observedAt = $date->timezone('America/Montevideo')->startOfDay();
        $wanted = $currencies->keyBy('code');

        return $this->parser->parse($this->download($observedAt))
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
            ->retry([100, 500, 1000], 0, function (Throwable $exception): bool {
                return $exception instanceof ConnectionException
                    || ($exception instanceof RequestException
                        && ($exception->response->serverError() || $exception->response->status() === 429));
            })
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
