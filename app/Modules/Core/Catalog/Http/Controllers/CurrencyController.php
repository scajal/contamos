<?php

declare(strict_types=1);

namespace App\Modules\Core\Catalog\Http\Controllers;

use App\Modules\Core\Catalog\Http\Requests\Currency\StoreRequest;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Support\Objects\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use InertiaUI\Modal\Modal;

class CurrencyController
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return Inertia::render('catalog/currencies/index', [
            'currencies' => Inertia::defer(
                fn (): LengthAwarePaginator => Currency::query()
                    ->orderBy('created_at', 'desc')
                    ->paginate(10),
            ),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Modal
    {
        return Inertia::modal('catalog/currencies/create')
            ->baseRoute('catalog.currencies.index');
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
}
