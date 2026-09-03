<?php

declare(strict_types=1);

namespace App\Modules\Core\Catalog\Services\Quote\Contracts;

use App\Modules\Core\Catalog\Services\Quote\AggregateQuoteProvider;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\Bind;
use Illuminate\Support\Collection;

#[Bind(AggregateQuoteProvider::class)]
interface QuoteProvider
{
    /**
     * Fetch quotes for the given currencies on the given date.
     *
     * Implementations must quote only the currencies they handle and
     * ignore the rest.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Modules\Core\Catalog\Models\Currency>  $currencies
     * @return \Illuminate\Support\Collection<int, array{code: string, observed_at: \Carbon\CarbonImmutable, rate: string}>
     */
    public function quotesOn(CarbonImmutable $date, Collection $currencies): Collection;
}
