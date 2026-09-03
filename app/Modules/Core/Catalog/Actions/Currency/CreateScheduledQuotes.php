<?php

declare(strict_types=1);

namespace App\Modules\Core\Catalog\Actions\Currency;

use App\Modules\Core\Catalog\Contracts\Currency\QuoteProvider;
use App\Modules\Core\Catalog\Enums\Currency\Quote\QuoteSource;
use App\Modules\Core\Catalog\Models\Currency;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CreateScheduledQuotes
{
    public function __construct(private QuoteProvider $provider) {}

    /**
     * Persist scheduled quotes returned by the registered quote providers
     * for the given date.
     */
    public function using(CarbonImmutable $date): int
    {
        return DB::transaction(function () use ($date): int {
            $currencies = Currency::query()
                ->where('quote_source', QuoteSource::Scheduled)
                ->get();
            $currenciesByCode = $currencies->keyBy('code');

            $created = 0;

            foreach ($this->provider->quotesOn($date, $currencies) as $quote) {
                /** @var \App\Modules\Core\Catalog\Models\Currency|null $currency */
                $currency = $currenciesByCode->get($quote['code']);

                if (! $currency instanceof Currency) {
                    continue;
                }

                $alreadyRecorded = $currency->quotes()
                    ->whereDate('observed_at', $date->timezone('America/Montevideo')->toDateString())
                    ->where('source', QuoteSource::Scheduled)
                    ->exists();

                if ($alreadyRecorded) {
                    continue;
                }

                $currency->quotes()->create([
                    'observed_at' => $quote['observed_at']->utc(),
                    'rate' => $quote['rate'],
                    'source' => QuoteSource::Scheduled,
                ]);

                $created++;
            }

            return $created;
        });
    }
}
