<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Controllers\Team;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Actions\Team\CreateTransaction;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Http\Requests\Team\Transaction\IndexRequest;
use App\Modules\Core\Finance\Http\Requests\Team\Transaction\StoreRequest;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Objects\Toast;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use InertiaUI\Modal\Modal;
use Spatie\QueryBuilder\QueryBuilder;

class TransactionController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Team $team, IndexRequest $request): Response
    {
        return Inertia::render('finance/teams/transactions/index', [
            'accounts' => $team->accounts()
                ->orderBy('name')
                ->get(),
            'currencies' => Currency::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'filters' => $request->validated('filter', []),
            'types' => TransactionType::cases(),
            'transactions' => Inertia::scroll(
                fn (): LengthAwarePaginator => QueryBuilder::for($team->transactions(), $request)
                    ->allowedFilters(...$request->filters())
                    ->with('account', 'currency', 'user')
                    ->orderBy('occurred_at', 'desc')
                    ->paginate(10)
                    ->withQueryString(),
            )->defer(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Team $team, TransactionType $type): Modal
    {
        return Inertia::modal('finance/teams/transactions/create', [
            'accounts' => $team->accounts()
                ->isActive()
                ->with(['currencies' => function (Relation $currencies): void {
                    $currencies->isActive(); // @phpstan-ignore-line argument.type
                }])
                ->get(),
            'type' => $type,
        ])->baseRoute('finance.teams.transactions.index', ['team' => $team->getKey()]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Team $team, StoreRequest $request, #[CurrentUser] User $user, CreateTransaction $createTransaction): RedirectResponse
    {
        // Create the transaction.
        $createTransaction
            ->for($team)
            ->by($user)
            ->using($request->validated());

        // Flash a success message to the session.
        Toast::make(trans('Saved'))
            ->flash();

        return back();
    }

    /**
     * Show the specified resource.
     */
    public function show(Team $team, Transaction $transaction): Modal
    {
        return Inertia::modal('finance/teams/transactions/show', [
            'transaction' => $transaction->load('account', 'currency', 'user'),
        ])->baseRoute('finance.teams.transactions.index', ['team' => $team->getKey()]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Team $team, Transaction $transaction): RedirectResponse
    {
        $transaction->delete();

        // Flash a success message to the session.
        Toast::make(trans('Deleted'))
            ->flash();

        return back();
    }
}
