<?php

declare(strict_types=1);

namespace App\Modules\Core\Catalog\Services\Quote\Actions\Currency;

use App\Modules\Core\Catalog\Enums\Currency\Quote\QuoteSource;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Catalog\Services\Quote\Contracts\QuoteProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CreateScheduledQuotes
{
    /**
     * Initialize the create scheduled quotes action with the given quote provider.
     */
    public function __construct(
        private readonly QuoteProvider $provider
    ) {}

    /**
     * Persist scheduled quotes returned by the registered quote providers
     * for the given date.
     */
    public function using(CarbonImmutable $date): int
    {
        return DB::transaction(function () use ($date): int {
            // Get the currencies that have a manual or scheduled quote source.
            $currencies = Currency::query()
                ->where('quote_source', QuoteSource::ManualOrScheduled)
                ->get();

            // Key the currencies by code.
            $currenciesByCode = $currencies->keyBy('code');

            $created = 0;

            // Loop through the quotes returned by the quote provider.
            foreach ($this->provider->quotesOn($date, $currencies) as $quote) {
                // Get the currency by code.
                /** @var \App\Modules\Core\Catalog\Models\Currency|null $currency */
                if (! ($currency = $currenciesByCode->get($quote['code'])) instanceof Currency) {
                    continue;
                }

                // Check if the currency has already been recorded for the given date.
                $alreadyRecorded = $currency->quotes()
                    ->whereDate('observed_at', $date->toDateString())
                    ->where('source', QuoteSource::ManualOrScheduled)
                    ->exists();

                // If the currency has already been recorded for the given date, skip it.
                if ($alreadyRecorded) {
                    continue;
                }

                // Create the quote.
                $currency->quotes()->create([
                    'observed_at' => $quote['observed_at']->utc(),
                    'rate' => $quote['rate'],
                    'source' => QuoteSource::ManualOrScheduled,
                ]);

                $created++;
            }

            return $created;
        });
    }
}
