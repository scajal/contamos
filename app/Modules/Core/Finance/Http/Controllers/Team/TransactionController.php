<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Controllers\Team;

use App\Modules\Billing\Enums\Feature;
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
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use InertiaUI\Modal\Modal;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class TransactionController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Team $team, IndexRequest $request): Response
    {
        $since = $this->historyWindowStart($team);

        return Inertia::render('finance/teams/transactions/index', [
            'accounts' => $team->accounts()->orderBy('name')->get(),
            'currencies' => Currency::query()->isActive()->orderBy('name')->get(),
            'filters' => $request->presentedFilters($since?->toDateString()),
            'history' => $this->history($team, $since),
            'tags' => $team->tags()->orderBy('name')->get(),
            'types' => Transaction::typeFilters(),
            'transactions' => Inertia::scroll(
                fn (): LengthAwarePaginator => QueryBuilder::for($this->visible($team, $since), $request)
                    ->allowedFilters(...$request->filters())
                    ->with('account', 'currency', 'user', 'tags', 'transfer.transactions.account')
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
        $this->assertTransactionIsVisible($team, $transaction);

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
        // A row the plan hides reports not found before anything
        // else, so it is never confirmed to exist.
        $this->assertTransactionIsVisible($team, $transaction);

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
     * The team's transactions the plan still shows. A plan with
     * unlimited history leaves the query untouched; a limited one
     * cuts at the window start with the same date-only comparison
     * the index date filters use.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Modules\Core\Finance\Models\Team\Transaction, \App\Modules\Core\Identity\Models\Team>
     */
    protected function visible(Team $team, ?CarbonImmutable $since): HasMany
    {
        $transactions = $team->transactions();

        // The cutoff is applied before any allowed filter, so no
        // filter the user sends can widen it.
        if ($since instanceof CarbonImmutable) {
            $transactions->occurredFrom($since->toDateString());
        }

        return $transactions;
    }

    /**
     * What the page needs to tell the team its list is cut: since
     * when it starts, how many months the plan keeps, and whether
     * the team has anything older to miss.
     *
     * @return array{has_older: bool, limited: bool, months: int|null, since: string|null}
     */
    protected function history(Team $team, ?CarbonImmutable $since): array
    {
        return [
            // Only a limited team can have anything hidden, so the
            // check is skipped entirely for a pro team.
            'has_older' => $since instanceof CarbonImmutable && $team->transactions()
                ->occurredUntil($since->subDay()->toDateString())
                ->exists(),
            'limited' => $since instanceof CarbonImmutable,
            'months' => Feature::ViewHistory->limitOn($team->plan()),
            'since' => $since?->toDateString(),
        ];
    }

    /**
     * The first day of history the team's plan still opens, or
     * null when the plan keeps everything.
     */
    protected function historyWindowStart(Team $team): ?CarbonImmutable
    {
        return Transaction::historyWindowStart(Feature::ViewHistory->limitOn($team->plan()));
    }

    /**
     * Refuse a transaction the team's plan no longer shows. It is
     * still there and still counts towards the balances, but a
     * hidden row must not be readable or writable by URL.
     */
    protected function assertTransactionIsVisible(Team $team, Transaction $transaction): void
    {
        $since = $this->historyWindowStart($team);

        abort_if(
            $since instanceof CarbonImmutable && $transaction->occurred_at->startOfDay()->lessThan($since),
            HttpResponse::HTTP_NOT_FOUND,
        );
    }

    /**
     * Refuse a transaction owned by a transfer, which can only be
     * edited or deleted through the transfer itself.
     */
    protected function assertTransactionIsNotOwnedByATransfer(Transaction $transaction): void
    {
        abort_if(
            filled($transaction->getAttribute($transaction->transfer()->getForeignKeyName())),
            HttpResponse::HTTP_FORBIDDEN,
            trans('This movement belongs to a transfer. Edit or delete the transfer instead.'),
        );
    }
}
