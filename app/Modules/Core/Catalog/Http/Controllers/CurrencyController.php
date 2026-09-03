<?php

declare(strict_types=1);

namespace App\Modules\Core\Catalog\Http\Controllers;

use App\Modules\Core\Catalog\Enums\Currency\CurrencyType;
use App\Modules\Core\Catalog\Enums\Currency\Quote\QuoteSource;
use App\Modules\Core\Catalog\Http\Requests\Currency\IndexRequest;
use App\Modules\Core\Catalog\Http\Requests\Currency\StoreRequest;
use App\Modules\Core\Catalog\Http\Requests\Currency\UpdateRequest;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Support\Objects\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use InertiaUI\Modal\Modal;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CurrencyController
{
    /**
     * Display a listing of the resource.
     */
    public function index(IndexRequest $request): Response
    {
        return Inertia::render('catalog/currencies/index', [
            'filters' => $request->array('filter'),
            'types' => CurrencyType::cases(),
            'currencies' => Inertia::defer(
                fn (): LengthAwarePaginator => QueryBuilder::for(Currency::class, $request)
                    ->allowedFilters(
                        AllowedFilter::exact('is_active'),
                        AllowedFilter::groupOr('search', [AllowedFilter::partial('name'), AllowedFilter::partial('code')]),
                        AllowedFilter::exact('type'),
                    )
                    ->orderBy('created_at', 'desc')
                    ->paginate(10)
                    ->withQueryString(),
            ),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Modal
    {
        return Inertia::modal('catalog/currencies/create', [
            'quoteSources' => QuoteSource::cases(),
            'types' => CurrencyType::cases(),
        ])->baseRoute('catalog.currencies.index');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRequest $request): RedirectResponse
    {
        // Store the currency with the validated data.
        Currency::query()->create($request->validated());

        // Show a message to the user.
        Toast::make(trans('Saved'))
            ->flash();

        return back();
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Currency $currency): Modal
    {
        return Inertia::modal('catalog/currencies/edit', [
            'currency' => $currency,
            'quoteSources' => QuoteSource::cases(),
            'types' => CurrencyType::cases(),
        ])->baseRoute('catalog.currencies.index');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRequest $request, Currency $currency): RedirectResponse
    {
        // Update the currency with the validated data.
        $currency->update($request->validated());

        // Show a message to the user.
        Toast::make(trans('Saved'))
            ->flash();

        return back();
    }

    /**
     * Activate the specified resource.
     */
    public function activate(Currency $currency): RedirectResponse
    {
        // Activate the currency.
        $currency->update(['is_active' => true]);

        // Show a message to the user.
        Toast::make(trans('Saved'))
            ->flash();

        return back();
    }

    /**
     * Deactivate the specified resource.
     */
    public function deactivate(Currency $currency): RedirectResponse
    {
        // Deactivate the currency.
        $currency->update(['is_active' => false]);

        // Show a message to the user.
        Toast::make(trans('Saved'))
            ->flash();

        return back();
    }
}
