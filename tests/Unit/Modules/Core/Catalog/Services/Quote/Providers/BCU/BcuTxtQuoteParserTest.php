<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Services\Quote\Exceptions\QuoteProviderException;
use App\Modules\Core\Catalog\Services\Quote\Providers\BCU\BcuTxtQuoteParser;

/**
 * Unit tests for \App\Modules\Core\Catalog\Services\Quote\Providers\BCU\BcuTxtQuoteParser.
 */
function bcuCotizacionesFixture(): string
{
    $contents = file_get_contents(base_path('tests/Fixtures/bcu-cotizaciones-2026-09-02.txt'));

    if (! is_string($contents)) {
        throw new InvalidArgumentException('Expected the BCU cotizaciones fixture to be readable.');
    }

    return $contents;
}

it('extracts international rates as units per one USD', function (): void {
    $quotes = (new BcuTxtQuoteParser)->parse(bcuCotizacionesFixture());

    expect($quotes->get('ARS'))->toBe('1510.590100')
        ->and($quotes->get('USD'))->toBe('1.000000');
});

it('inverts double-asterisk international rates so one USD equals the currency', function (): void {
    $quotes = (new BcuTxtQuoteParser)->parse(bcuCotizacionesFixture());

    expect($quotes->get('EUR'))->toBe(bcdiv('1', '1.158600', 18));
});

it('adds local UYU UI UP and UR rates that are missing from the international section', function (): void {
    $quotes = (new BcuTxtQuoteParser)->parse(bcuCotizacionesFixture());

    expect($quotes->get('UYU'))->toBe('40.233')
        ->and($quotes->get('UI'))->toBe('6.63740')
        ->and($quotes->get('UP'))->toBe('1.79500')
        ->and($quotes->get('UR'))->toBe('1940.770');
});

it('does not replace the international ARS rate with the local ARS bill in UYU', function (): void {
    $quotes = (new BcuTxtQuoteParser)->parse(bcuCotizacionesFixture());

    expect($quotes->get('ARS'))->toBe('1510.590100')
        ->and($quotes->contains('0.027'))->toBeFalse();
});

it('fails when the international section is missing', function (): void {
    (new BcuTxtQuoteParser)->parse("C O T I Z A C I O N E S    I N T E R B A N C A R I A S\nM E T A L E S    P R E C I O S O S\n");
})->throws(QuoteProviderException::class);

it('fails when the local section is missing', function (): void {
    (new BcuTxtQuoteParser)->parse("M E R C A D O    D E    C A M B I O S    I N T E R N A C I O N A L\n");
})->throws(QuoteProviderException::class);
