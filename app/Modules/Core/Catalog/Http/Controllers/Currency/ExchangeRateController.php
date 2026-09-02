<?php

declare(strict_types=1);

namespace App\Modules\Core\Catalog\Http\Controllers\Currency;

use App\Modules\Core\Catalog\Http\Requests\Currency\StoreRequest;
use App\Modules\Core\Catalog\Http\Requests\Currency\UpdateRequest;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Support\Objects\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use InertiaUI\Modal\Modal;

class ExchangeRateController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Currency $currency): Response
    {
        return Inertia::render('catalog/currencies/exchange-rates/index', [
            'currency' => $currency,
            'exchangeRates' => Inertia::defer(
                fn (): LengthAwarePaginator => $currency->exchangeRates()
                    ->orderBy('created_at', 'desc')
                    ->paginate(10),
            ),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Currency $currency): Modal
    {
        return Inertia::modal('catalog/currencies/exchange-rates/create')
            ->baseRoute('catalog.currencies.exchange-rates.index', ['currency' => $currency->getKey()]);
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
}
