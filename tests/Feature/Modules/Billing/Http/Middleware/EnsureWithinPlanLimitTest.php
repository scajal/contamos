<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team\TeamInvitation;
use App\Modules\Core\Identity\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Paddle\Cashier;

/**
 * Feature tests for \App\Modules\Billing\Http\Middleware\EnsureWithinPlanLimit.
 */
describe('accounts', function (): void {
    it('redirects a free team at the account limit to the billing page', function (): void {
        [$user, $team] = actingMember();
        Account::factory()->for($team)->count(3)->create();

        $this->get(route('finance.teams.accounts.create', ['team' => $team->getRouteKey()]))
            ->assertRedirectToRoute('billing.teams.subscriptions.index', ['team' => $team->getRouteKey()]);

        assertFlashedToast(trans('Upgrade to Pro to add more :feature.', ['feature' => 'accounts']));
    });

    it('lets a pro team past the account limit', function (): void {
        Cashier::fake();
        [$user, $team] = actingMember();
        Account::factory()->for($team)->count(3)->create();
        $team->subscriptions()->create(['type' => 'default', 'paddle_id' => 'sub_test', 'status' => 'active'])
            ->items()->create(['product_id' => 'pro_test', 'price_id' => 'pri_month', 'status' => 'active', 'quantity' => 1]);

        $this->get(route('finance.teams.accounts.create', ['team' => $team->getRouteKey()]))
            ->assertOk();
    });
});

describe('members', function (): void {
    it('redirects a free team at the member limit to the billing page', function (): void {
        [$user, $team] = actingMember();
        $team->users()->attach(User::factory()->create());

        $this->get(route('identity.teams.invitations.create', ['team' => $team->getRouteKey()]))
            ->assertRedirectToRoute('billing.teams.subscriptions.index', ['team' => $team->getRouteKey()]);

        assertFlashedToast(trans('Upgrade to Pro to add more :feature.', ['feature' => 'members']));
    });

    it('counts pending invitations toward the member limit', function (): void {
        [$user, $team] = actingMember();
        TeamInvitation::factory()->for($team)->create();

        $this->get(route('identity.teams.invitations.create', ['team' => $team->getRouteKey()]))
            ->assertRedirectToRoute('billing.teams.subscriptions.index', ['team' => $team->getRouteKey()]);
    });
});

describe('transactions', function (): void {
    it('redirects a free team at the monthly transaction limit to the billing page', function (): void {
        [$user, $team] = actingMember();
        $account = Account::factory()->for($team)->create();
        Transaction::factory()->for($account)->for($team)->count(100)->create(['created_at' => CarbonImmutable::now()]);

        $this->get(route('finance.teams.transactions.create', ['team' => $team->getRouteKey(), 'type' => TransactionType::Expense->value]))
            ->assertRedirectToRoute('billing.teams.subscriptions.index', ['team' => $team->getRouteKey()]);

        assertFlashedToast(trans('Upgrade to Pro to add more :feature.', ['feature' => 'transactions']));
    });

    it('does not count transactions created outside the current calendar month', function (): void {
        [$user, $team] = actingMember();
        $account = Account::factory()->for($team)->create();
        Transaction::factory()->for($account)->for($team)->count(100)->create(['created_at' => CarbonImmutable::now()->subMonth()]);

        $this->get(route('finance.teams.transactions.create', ['team' => $team->getRouteKey(), 'type' => TransactionType::Expense->value]))
            ->assertOk();
    });
});
