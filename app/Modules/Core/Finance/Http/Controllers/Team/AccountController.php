<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Controllers\Team;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Actions\Team\CreateAccount;
use App\Modules\Core\Finance\Actions\Team\UpdateAccount;
use App\Modules\Core\Finance\Enums\Account\AccountType;
use App\Modules\Core\Finance\Http\Requests\Team\Account\StoreRequest;
use App\Modules\Core\Finance\Http\Requests\Team\Account\UpdateRequest;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Support\Objects\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use InertiaUI\Modal\Modal;

class AccountController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Team $team): Response
    {
        return Inertia::render('finance/teams/accounts/index', [
            'accounts' => Inertia::defer(
                fn (): LengthAwarePaginator => $team->accounts()->paginate(10)
            ),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Team $team): Modal
    {
        return Inertia::modal('finance/teams/accounts/create', [
            'currencies' => Currency::query()
                ->where('is_active', true)
                ->get(),
            'types' => AccountType::cases(),
        ])->baseRoute('finance.teams.accounts.index', ['team' => $team->getKey()]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Team $team, StoreRequest $request, CreateAccount $createAccount): RedirectResponse
    {
        // Create the account with the validated data.
        $createAccount
            ->for($team)
            ->using($request->safe()->only(['name', 'type']), $request->validated('currency_ids')); // @phpstan-ignore-line argument.type

        // Flash a success message to the session.
        Toast::make(trans('Saved'))
            ->flash();

        return back();
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Team $team, Account $account): Modal
    {
        return Inertia::modal('finance/teams/accounts/edit', [
            'account' => $account->load('currencies:'.new Currency()->getKeyName()),
            'currencies' => Currency::query()->get(),
            'types' => AccountType::cases(),
        ])->baseRoute('finance.teams.accounts.index', ['team' => $team->getKey()]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Team $team, Account $account, UpdateRequest $request, UpdateAccount $updateAccount): RedirectResponse
    {
        // Update the account with the validated data.
        $updateAccount
            ->for($account)
            ->using($request->safe()->only(['name', 'type']), $request->validated('currency_ids')); // @phpstan-ignore-line argument.type

        // Flash a success message to the session.
        Toast::make(trans('Saved'))
            ->flash();

        return back();
    }

    /**
     * Activate the specified resource.
     */
    public function activate(Team $team, Account $account): RedirectResponse
    {
        // Activate the account.
        $account->update(['is_active' => true]);

        // Show a message to the user.
        Toast::make(trans('Saved'))
            ->flash();

        return back();
    }

    /**
     * Deactivate the specified resource.
     */
    public function deactivate(Team $team, Account $account): RedirectResponse
    {
        // Deactivate the account.
        $account->update(['is_active' => false]);

        // Show a message to the user.
        Toast::make(trans('Saved'))
            ->flash();

        return back();
    }
}
