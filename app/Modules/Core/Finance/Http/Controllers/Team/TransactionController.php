<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Controllers\Team;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Actions\Team\CreateTransaction;
use App\Modules\Core\Finance\Actions\Team\DeleteTransaction;
use App\Modules\Core\Finance\Actions\Team\UpdateTransaction;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Http\Requests\Team\Transaction\IndexRequest;
use App\Modules\Core\Finance\Http\Requests\Team\Transaction\StoreRequest;
use App\Modules\Core\Finance\Http\Requests\Team\Transaction\UpdateRequest;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Objects\Toast;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
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
            'accounts' => $team->accounts()->orderBy('name')->get(),
            'currencies' => Currency::query()->isActive()->orderBy('name')->get(),
            'filters' => $request->presentedFilters(),
            'tags' => $team->tags()->orderBy('name')->get(),
            'types' => [
                TransactionType::Income->value,
                TransactionType::Expense->value,
                TransactionType::OpeningBalance->value,
                Transaction::TRANSFER_TYPE,
            ],
            'transactions' => Inertia::scroll(
                fn (): LengthAwarePaginator => QueryBuilder::for($team->transactions(), $request)
                    ->allowedFilters(...$request->filters())
                    ->with('account', 'currency', 'user', 'tags')
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
            'accounts' => $team->accounts()->isActive()->with(['currencies' => function (Relation $currencies): void {
                $currencies->isActive()->with('quotes'); // @phpstan-ignore-line argument.type
            }])->get(),
            'tags' => $team->tags()->isActive()->orderBy('name')->get(),
            'type' => $type,
        ])->baseRoute('finance.teams.transactions.index', ['team' => $team->getRouteKey()]);
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
            ->using($request->safe([
                (new Transaction)->account()->getForeignKeyName(),
                'amount',
                (new Transaction)->currency()->getForeignKeyName(),
                'description',
                'occurred_at',
                'quote',
                'type',
            ]), $request->tagIds());

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
            'transaction' => $transaction->load('account', 'currency', 'user', 'tags'),
        ])->baseRoute('finance.teams.transactions.index', ['team' => $team->getRouteKey()]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Team $team, Transaction $transaction): Modal
    {
        $this->assertTransactionIsNotOwnedByATransfer($transaction);

        $transaction->load('account', 'currency', 'user', 'tags');

        return Inertia::modal('finance/teams/transactions/edit', [
            'accounts' => $team->accounts()
                ->where(fn (Builder $accounts): Builder => $accounts->isActive()->orWhereKey($transaction->account_id))
                ->with(['currencies' => function (Relation $currencies) use ($transaction): void {
                    $currencies->where(fn (Builder $query): Builder => $query->isActive()->orWhereKey($transaction->currency_id))->with('quotes'); // @phpstan-ignore-line argument.type
                }])
                ->get(),
            'tags' => $team->tags()
                ->where(fn (Builder $tags): Builder => $tags->isActive()->orWhereIn(new Tag()->getKeyName(), $transaction->tags->pluck(new Tag()->getKeyName())))
                ->orderBy('name')
                ->get(),
            'transaction' => $transaction,
        ])->baseRoute('finance.teams.transactions.index', ['team' => $team->getRouteKey()]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Team $team, Transaction $transaction, UpdateRequest $request, UpdateTransaction $updateTransaction): RedirectResponse
    {
        $this->assertTransactionIsNotOwnedByATransfer($transaction);

        // Update the transaction.
        $updateTransaction
            ->for($team)
            ->using($transaction, $request->safe([
                (new Transaction)->account()->getForeignKeyName(),
                'amount',
                (new Transaction)->currency()->getForeignKeyName(),
                'description',
                'occurred_at',
                'quote',
                'type',
            ]), $request->tagIds());

        // Flash a success message to the session.
        Toast::make(trans('Saved'))
            ->flash();

        return back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Team $team, Transaction $transaction, DeleteTransaction $deleteTransaction): RedirectResponse
    {
        $this->assertTransactionIsNotOwnedByATransfer($transaction);

        // Delete the transaction and revert its balance effect.
        $deleteTransaction
            ->for($team)
            ->using($transaction);

        // Flash a success message to the session.
        Toast::make(trans('Deleted'))
            ->flash();

        return back();
    }

    /**
     * Refuse a transaction owned by a transfer, which can only be
     * edited or deleted through the transfer itself.
     */
    protected function assertTransactionIsNotOwnedByATransfer(Transaction $transaction): void
    {
        if (blank($transaction->getAttribute($transaction->transfer()->getForeignKeyName()))) {
            return;
        }

        throw ValidationException::withMessages([
            'transfer' => trans('This movement belongs to a transfer. Edit or delete the transfer instead.'),
        ]);
    }
}
