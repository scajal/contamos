<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Enums\Currency\Quote\QuoteSource;
use App\Modules\Core\Catalog\Exceptions\QuoteProviderException;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Catalog\Models\Currency\Quote;
use App\Modules\Core\Catalog\QuoteProviders\BcuTxtQuoteProvider;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;

/**
 * Feature tests for \App\Modules\Core\Catalog\Console\Commands\SyncScheduledQuotesCommand.
 */
function fakeBcuCotizaciones(string|int $response = ''): void
{
    $body = is_int($response)
        ? 'error'
        : ($response !== '' ? $response : (string) file_get_contents(base_path('tests/Fixtures/bcu-cotizaciones-2026-09-02.txt')));

    $status = is_int($response) ? $response : 200;

    Http::fake([
        BcuTxtQuoteProvider::URL.'*' => Http::response($body, $status),
    ]);
}

it('creates scheduled quotes for currencies the BCU file includes', function (): void {
    fakeBcuCotizaciones();

    $ars = Currency::factory()->fiat()->scheduled()->create(['code' => 'ARS']);
    $eur = Currency::factory()->fiat()->scheduled()->create(['code' => 'EUR']);
    $uyu = Currency::factory()->fiat()->scheduled()->create(['code' => 'UYU']);
    $manualUyu = Currency::factory()->fiat()->create([
        'code' => 'UYI',
        'quote_source' => QuoteSource::ManualOrScheduled,
    ]);
    Currency::factory()->fiat()->scheduled()->create(['code' => 'XYZ']);
    Currency::factory()->crypto()->scheduled()->create(['code' => 'BTC']);

    $this->artisan('catalog:sync-scheduled-quotes', ['--date' => '2026-09-02'])
        ->assertSuccessful();

    expect($ars->quotes()->count())->toBe(1)
        ->and($eur->quotes()->count())->toBe(1)
        ->and($uyu->quotes()->count())->toBe(1)
        ->and($manualUyu->quotes()->count())->toBe(0)
        ->and(Quote::query()->where('source', QuoteSource::ManualOrScheduled)->count())->toBe(3);

    $arsQuote = $ars->quotes()->first();
    $eurQuote = $eur->quotes()->first();
    $uyuQuote = $uyu->quotes()->first();

    expect($arsQuote)->not->toBeNull()
        ->and(bccomp((string) $arsQuote->rate, '1510.590100', 6))->toBe(0)
        ->and($eurQuote)->not->toBeNull()
        ->and(bccomp((string) $eurQuote->rate, bcdiv('1', '1.158600', 18), 12))->toBe(0)
        ->and($uyuQuote)->not->toBeNull()
        ->and(bccomp((string) $uyuQuote->rate, '40.233', 6))->toBe(0)
        ->and($uyuQuote->recorded_by)->toBeNull();

    expect($arsQuote->observed_at->equalTo(
        CarbonImmutable::parse('2026-09-02 00:00:00', 'America/Montevideo'),
    ))->toBeTrue();
});

it('stores observed_at on the America/Montevideo calendar day', function (): void {
    fakeBcuCotizaciones();

    $this->travelTo(CarbonImmutable::parse('2026-09-03 17:35:00', 'America/Montevideo'));

    $ars = Currency::factory()->fiat()->scheduled()->create(['code' => 'ARS']);

    $this->artisan('catalog:sync-scheduled-quotes')
        ->assertSuccessful();

    $quote = $ars->quotes()->first();

    expect($quote)->not->toBeNull()
        ->and($quote->observed_at->equalTo(
            CarbonImmutable::parse('2026-09-03 00:00:00', 'America/Montevideo'),
        ))->toBeTrue();
});

it('does not duplicate scheduled quotes for the same day', function (): void {
    fakeBcuCotizaciones();

    $ars = Currency::factory()->fiat()->scheduled()->create(['code' => 'ARS']);

    $this->artisan('catalog:sync-scheduled-quotes', ['--date' => '2026-09-02'])
        ->assertSuccessful();
    $this->artisan('catalog:sync-scheduled-quotes', ['--date' => '2026-09-02'])
        ->assertSuccessful();

    expect($ars->quotes()->count())->toBe(1);
});

it('fails when the BCU request returns HTTP 500', function (): void {
    fakeBcuCotizaciones(500);

    Currency::factory()->fiat()->scheduled()->create(['code' => 'ARS']);

    $this->artisan('catalog:sync-scheduled-quotes', ['--date' => '2026-09-02'])
        ->assertFailed();

    expect(Quote::query()->count())->toBe(0);
});

it('fails when the BCU service is unavailable', function (): void {
    fakeBcuCotizaciones('Servicio no disponible');

    Currency::factory()->fiat()->scheduled()->create(['code' => 'ARS']);

    $this->artisan('catalog:sync-scheduled-quotes', ['--date' => '2026-09-02'])
        ->assertFailed();

    Exceptions::assertReported(
        fn (QuoteProviderException $exception): bool => $exception->getMessage() === 'The BCU cotizaciones service is unavailable.',
    );

    expect(Quote::query()->count())->toBe(0);
});

it('sends the quote date as Fecha in the BCU query string', function (): void {
    fakeBcuCotizaciones();

    Currency::factory()->fiat()->scheduled()->create(['code' => 'USD']);

    $this->artisan('catalog:sync-scheduled-quotes', ['--date' => '2026-09-02'])
        ->assertSuccessful();

    Http::assertSent(function (Request $request): bool {
        $pairs = json_decode((string) $request->data()['KeyValuePairs'], true);

        return str_starts_with($request->url(), BcuTxtQuoteProvider::URL)
            && ($request->data()['op'] ?? null) === 'downloadcotizacionestxt'
            && is_array($pairs)
            && ($pairs['KeyValuePairs']['Fecha'] ?? null) === '02/09/2026';
    });
});
