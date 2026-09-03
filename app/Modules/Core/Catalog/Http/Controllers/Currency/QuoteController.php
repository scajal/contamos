<?php

declare(strict_types=1);

namespace App\Modules\Core\Catalog\Http\Controllers\Currency;

use App\Modules\Core\Catalog\Enums\Currency\Quote\QuoteSource;
use App\Modules\Core\Catalog\Http\Requests\Currency\Quote\IndexRequest;
use App\Modules\Core\Catalog\Http\Requests\Currency\Quote\StoreRequest;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Catalog\Models\Currency\Quote;
use App\Modules\Support\Objects\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use InertiaUI\Modal\Modal;
use Spatie\QueryBuilder\QueryBuilder;

class QuoteController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Currency $currency, IndexRequest $request): Response
    {
        return Inertia::render('catalog/currencies/quotes/index', [
            'currency' => $currency,
            'filters' => $request->validated('filter', []),
            'quotes' => Inertia::defer(
                fn (): LengthAwarePaginator => QueryBuilder::for($currency->quotes(), $request)
                    ->allowedFilters(...$request->filters())
                    ->orderBy('created_at', 'desc')
                    ->paginate(10)
                    ->withQueryString(),
            ),
            'sources' => QuoteSource::cases(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Currency $currency): Modal
    {
        return Inertia::modal('catalog/currencies/quotes/create', [
            'currency' => $currency,
        ])->baseRoute('catalog.currencies.quotes.index', ['currency' => $currency->getKey()]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Currency $currency, StoreRequest $request): RedirectResponse
    {
        // Store the currency with the validated data.
        $currency->quotes()->create($request->validated());

        // Show a message to the user.
        Toast::make(trans('Saved'))
            ->flash();

        return back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Currency $currency, Quote $quote): RedirectResponse
    {
        // Delete the quote.
        $quote->delete();

        // Show a message to the user.
        Toast::make(trans('Deleted'))
            ->flash();

        return back();
    }
}
