<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Controllers\Team;

use App\Modules\Core\Finance\Enums\Account\AccountType;
use App\Modules\Core\Finance\Http\Requests\Team\Account\StoreRequest;
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
            'types' => AccountType::cases(),
        ])->baseRoute('finance.teams.accounts.index', ['team' => $team->getKey()]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Team $team, StoreRequest $request): RedirectResponse
    {
        // Create the account with the validated data.
        $team->accounts()->create($request->validated());

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
            'account' => $account,
            'types' => AccountType::cases(),
        ])->baseRoute('finance.teams.accounts.index', ['team' => $team->getKey()]);
    }
}
