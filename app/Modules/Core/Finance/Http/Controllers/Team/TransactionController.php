<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Controllers\Team;

use App\Modules\Core\Finance\Http\Requests\Team\Transaction\StoreRequest;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Support\Objects\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use InertiaUI\Modal\Modal;

class TransactionController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Team $team): Response
    {
        return Inertia::render('finance/teams/transactions/index', [
            'transactions' => Inertia::defer(
                fn (): LengthAwarePaginator => $team->transactions()
                    ->with('account', 'currency')
                    ->paginate(10),
            ),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Team $team): Modal
    {
        return Inertia::modal('finance/teams/transactions/create', [
            'accounts' => $team->accounts()
                ->with('currencies')
                ->get(),
        ])->baseRoute('finance.teams.transactions.index', ['team' => $team->getKey()]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Team $team, StoreRequest $request): RedirectResponse
    {
        // Create the transaction.
        $team->transactions()->create($request->validated());

        // Flash a success message to the session.
        Toast::make(trans('Saved'))
            ->flash();

        return back();
    }
}
