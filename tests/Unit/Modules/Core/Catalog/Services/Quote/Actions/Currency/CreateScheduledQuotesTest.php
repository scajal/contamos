<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Catalog\Models\Currency\Quote;
use App\Modules\Core\Catalog\Services\Quote\Actions\Currency\CreateScheduledQuotes;
use App\Modules\Core\Catalog\Services\Quote\Contracts\QuoteProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Unit tests for \App\Modules\Core\Catalog\Services\Quote\Actions\Currency\CreateScheduledQuotes.
 */
it('skips provider quotes whose currency code is not scheduled', function (): void {
    Currency::factory()->fiat()->scheduled()->create(['code' => 'ARS']);

    $provider = new class implements QuoteProvider
    {
        public function quotesOn(CarbonImmutable $date, Collection $currencies): Collection
        {
            return collect([
                [
                    'code' => 'ZZZ',
                    'observed_at' => $date,
                    'rate' => '1',
                ],
            ]);
        }
    };

    $created = (new CreateScheduledQuotes($provider))->using(CarbonImmutable::parse('2026-09-02'));

    expect($created)->toBe(0)
        ->and(Quote::query()->count())->toBe(0);
});
