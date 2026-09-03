<?php

declare(strict_types=1);

namespace App\Modules\Core\Catalog\Services\Quote;

use App\Modules\Core\Catalog\Services\Quote\Contracts\QuoteProvider;
use App\Modules\Core\Catalog\Services\Quote\Providers\BCU\BcuTxtQuoteProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AggregateQuoteProvider implements Contracts\QuoteProvider
{
    /**
     * Registered quote providers, in precedence order.
     *
     * @var list<\App\Modules\Core\Catalog\Services\Quote\Contracts\QuoteProvider>
     */
    private array $providers;

    /**
     * Initialize the aggregate quote provider with the given quote providers.
     */
    public function __construct(BcuTxtQuoteProvider $bcu)
    {
        $this->providers = [$bcu];
    }

    /**
     * Merge quotes from every registered provider. The first provider that
     * returns a given currency code wins.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Modules\Core\Catalog\Models\Currency>  $currencies
     * @return \Illuminate\Support\Collection<int, array{code: string, observed_at: \Carbon\CarbonImmutable, rate: string}>
     */
    public function quotesOn(CarbonImmutable $date, Collection $currencies): Collection
    {
        return collect($this->providers)
            ->flatMap(fn (QuoteProvider $provider): Collection => $provider->quotesOn($date, $currencies))
            ->unique('code')
            ->values();
    }
}
