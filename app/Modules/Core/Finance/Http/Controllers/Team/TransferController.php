<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Controllers\Team;

use App\Modules\Core\Finance\Actions\Team\CreateTransfer;
use App\Modules\Core\Finance\Actions\Team\DeleteTransfer;
use App\Modules\Core\Finance\Actions\Team\UpdateTransfer;
use App\Modules\Core\Finance\Http\Requests\Team\Transfer\StoreRequest;
use App\Modules\Core\Finance\Http\Requests\Team\Transfer\UpdateRequest;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Finance\Models\Team\Transfer;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Objects\Toast;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use InertiaUI\Modal\Modal;

class TransferController
{
    /**
     * The attributes of a transfer that the write actions take.
     *
     * @var array<int, string>
     */
    protected const array ATTRIBUTES = [
        'amount_received',
        'amount_sent',
        'description',
        'destination_account_id',
        'destination_currency_id',
        'destination_fee',
        'destination_quote',
        'occurred_at',
        'origin_account_id',
        'origin_currency_id',
        'origin_fee',
        'origin_quote',
    ];

    /**
     * Show the form for creating a new resource.
     */
    public function create(Team $team): Modal
    {
        return Inertia::modal('finance/teams/transfers/create', [
            'accounts' => $team->accounts()->isActive()->with(['currencies' => function (Relation $currencies): void {
                $currencies->isActive()->with('quotes'); // @phpstan-ignore-line argument.type
            }])->get(),
            'tags' => $team->tags()->isActive()->orderBy('name')->get(),
        ])->baseRoute('finance.teams.transactions.index', ['team' => $team->getRouteKey()]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Team $team, StoreRequest $request, #[CurrentUser] User $user, CreateTransfer $createTransfer): RedirectResponse
    {
        // Create the transfer along with the transactions it owns.
        $transfer = $createTransfer
            ->for($team)
            ->by($user)
            ->using($request->safe(self::ATTRIBUTES), $request->tagIds());

        // Flash a success message to the session, offering to take the
        // transfer back with both of its legs.
        Toast::make(trans('Saved'))
            ->action(trans('Undo'), route('finance.teams.transfers.destroy', [
                'team' => $team->getRouteKey(),
                'transfer' => $transfer->getRouteKey(),
            ]))
            ->flash();

        return back();
    }

    /**
     * Show the specified resource.
     */
    public function show(Team $team, Transfer $transfer): Modal
    {
        return Inertia::modal('finance/teams/transfers/show', [
            'transfer' => $transfer->load('user', 'tags', 'transactions.account', 'transactions.currency'),
        ])->baseRoute('finance.teams.transactions.index', ['team' => $team->getRouteKey()]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Team $team, Transfer $transfer): Modal
    {
        $transfer->load('user', 'tags', 'transactions.account', 'transactions.currency');

        // Keys the transfer already points at, which stay available
        // even if they have since been deactivated.
        $accountIds = $transfer->transactions->pluck(new Transaction()->account()->getForeignKeyName())->unique()->all();
        $currencyIds = $transfer->transactions->pluck(new Transaction()->currency()->getForeignKeyName())->unique()->all();

        return Inertia::modal('finance/teams/transfers/edit', [
            'accounts' => $team->accounts()
                ->where(fn (Builder $accounts): Builder => $accounts->isActive()->orWhereKey($accountIds))
                ->with(['currencies' => function (Relation $currencies) use ($currencyIds): void {
                    $currencies->where(fn (Builder $query): Builder => $query->isActive()->orWhereKey($currencyIds))->with('quotes'); // @phpstan-ignore-line argument.type
                }])
                ->get(),
            'tags' => $this->tags($team, $transfer),
            'transfer' => $transfer,
        ])->baseRoute('finance.teams.transactions.index', ['team' => $team->getRouteKey()]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Team $team, Transfer $transfer, UpdateRequest $request, UpdateTransfer $updateTransfer): RedirectResponse
    {
        // Update the transfer along with the transactions it owns.
        $updateTransfer
            ->for($team)
            ->using($transfer, $request->safe(self::ATTRIBUTES), $request->tagIds());

        // Flash a success message to the session.
        Toast::make(trans('Saved'))
            ->flash();

        return back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Team $team, Transfer $transfer, DeleteTransfer $deleteTransfer): RedirectResponse
    {
        // Delete the transfer and revert the balance effect of every
        // transaction it owns.
        $deleteTransfer
            ->for($team)
            ->using($transfer);

        // Flash a success message to the session.
        Toast::make(trans('Deleted'))
            ->flash();

        return back();
    }

    /**
     * Get the tags the transfer can be edited with, including the ones
     * already attached even when they have been deactivated.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Finance\Models\Team\Tag>
     */
    protected function tags(Team $team, Transfer $transfer): Collection
    {
        return $team->tags()
            ->where(fn (Builder $tags): Builder => $tags->isActive()->orWhereIn(new Tag()->getKeyName(), $transfer->tags->pluck(new Tag()->getKeyName())))
            ->orderBy('name')
            ->get();
    }
}
