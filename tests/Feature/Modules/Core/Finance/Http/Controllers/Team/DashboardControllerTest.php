<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Feature tests for \App\Modules\Core\Finance\Http\Controllers\Team\DashboardController
 * and \App\Modules\Core\Identity\Http\Middleware\EnsureTeamMembership.
 */
it('redirects guests to login', function (): void {
    $team = Team::factory()->create();

    $this->get(route('finance.teams.dashboard.index', ['team' => $team->getRouteKey()]))
        ->assertRedirectToRoute('login');
});

it('renders the dashboard for a team member', function (): void {
    [$user, $team] = actingMember();

    $this->get(route('finance.teams.dashboard.index', ['team' => $team->getRouteKey()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
            ->component('finance/teams/dashboard')
            ->has('user')
            ->where('user.id', $user->getKey())
            ->where('user.current_team.slug', $team->getRouteKey())
            ->where('has_accounts', false)
            ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->has('balances', 0)
                ->has('recent_transactions', 0)));
});

it('renders the signed balance per currency of the active accounts', function (): void {
    [$user, $team] = actingMember();
    $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS', 'decimal_places' => 2, 'symbol' => '$']);
    $dollar = Currency::factory()->active()->fiat()->create(['code' => 'USD', 'decimal_places' => 2, 'symbol' => 'US$']);
    $active = Account::factory()->bank()->active()->for($team)->create();
    $inactive = Account::factory()->cash()->inactive()->for($team)->create();
    $foreign = Account::factory()->bank()->active()->for(Team::factory())->create();

    Transaction::factory()->income()->for($active)->for($peso)->create(['amount' => '100.50']);
    Transaction::factory()->expense()->for($active)->for($peso)->create(['amount' => '30.25']);
    Transaction::factory()->expense()->for($active)->for($dollar)->create(['amount' => '10']);
    Transaction::factory()->income()->for($inactive)->for($peso)->create(['amount' => '500']);
    Transaction::factory()->income()->for($foreign)->for($peso)->create(['amount' => '900']);

    $this->get(route('finance.teams.dashboard.index', ['team' => $team->getRouteKey()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
            ->component('finance/teams/dashboard')
            ->where('has_accounts', true)
            ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->has('balances', 2)
                ->where('balances.0.code', 'ARS')
                ->where('balances.0.symbol', '$')
                ->where('balances.0.decimal_places', 2)
                ->where('balances.0.balance', fn (mixed $balance): bool => (float) $balance === 70.25)
                ->where('balances.1.code', 'USD')
                ->where('balances.1.balance', fn (mixed $balance): bool => (float) $balance === -10.0)
                ->has('recent_transactions', 3)
                ->has('recent_transactions.0.account')
                ->has('recent_transactions.0.currency')));
});

it('adds an opening balance to the balance per currency and shows it in recent activity', function (): void {
    [$user, $team] = actingMember();
    $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS', 'decimal_places' => 2, 'symbol' => '$']);
    $active = Account::factory()->bank()->active()->for($team)->create();

    Transaction::factory()->openingBalance()->for($active)->for($peso)->create([
        'amount' => '100',
        'occurred_at' => now()->subDay(),
    ]);
    Transaction::factory()->expense()->for($active)->for($peso)->create([
        'amount' => '30',
        'occurred_at' => now(),
    ]);

    $this->get(route('finance.teams.dashboard.index', ['team' => $team->getRouteKey()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
            ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->has('balances', 1)
                ->where('balances.0.balance', fn (mixed $balance): bool => (float) $balance === 70.0)
                ->has('recent_transactions', 2)
                ->where('recent_transactions.1.type', 'opening_balance')));
});

it('does not count inactive accounts toward the empty path', function (): void {
    [, $team] = actingMember();
    Account::factory()->bank()->inactive()->for($team)->create();

    $this->get(route('finance.teams.dashboard.index', ['team' => $team->getRouteKey()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
            ->where('has_accounts', false));
});

it('renders the eight most recent transactions of the active accounts', function (): void {
    [$user, $team] = actingMember();
    $peso = Currency::factory()->active()->fiat()->create();
    $active = Account::factory()->bank()->active()->for($team)->create();
    $inactive = Account::factory()->cash()->inactive()->for($team)->create();
    $foreign = Account::factory()->bank()->active()->for(Team::factory())->create();

    $transactions = Transaction::factory()
        ->income()
        ->for($active)
        ->for($peso)
        ->count(9)
        ->sequence(fn (Sequence $sequence): array => [
            'occurred_at' => now()->subMinutes($sequence->index),
            'user_id' => $user->getKey(),
        ])
        ->create();

    Transaction::factory()->income()->for($inactive)->for($peso)->create([
        'occurred_at' => now()->addHour(),
        'user_id' => $user->getKey(),
    ]);
    Transaction::factory()->income()->for($foreign)->for($peso)->create([
        'occurred_at' => now()->addHour(),
    ]);

    $this->get(route('finance.teams.dashboard.index', ['team' => $team->getRouteKey()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
            ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->has('recent_transactions', 8)
                ->where('recent_transactions.0.id', $transactions[0]->getKey())
                ->where('recent_transactions.7.id', $transactions[7]->getKey())));
});

it('returns 404 when the team does not exist', function (): void {
    actingMember();

    $this->get(route('finance.teams.dashboard.index', ['team' => (string) Str::ulid()]))
        ->assertNotFound();
});

it('redirects a user with no teams to team creation', function (): void {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $this->actingAs($user);

    $this->get(route('finance.teams.dashboard.index', ['team' => $team->getRouteKey()]))
        ->assertRedirectToRoute('identity.teams.create');
});

it('forbids a user who belongs to another team', function (): void {
    actingMember();
    $foreignTeam = Team::factory()->create();

    $this->get(route('finance.teams.dashboard.index', ['team' => $foreignTeam->getRouteKey()]))
        ->assertForbidden();
});

it('switches the current team when the route team differs', function (): void {
    [$user, $currentTeam] = actingMember();
    $otherTeam = Team::factory()->create();
    $user->teams()->attach($otherTeam);

    expect($user->fresh()?->current_team_id)->toBe($currentTeam->getKey());

    $this->get(route('finance.teams.dashboard.index', ['team' => $otherTeam->getRouteKey()]))
        ->assertOk();

    expect($user->fresh()?->current_team_id)->toBe($otherTeam->getKey());
});

it('shares the member teams on the dashboard', function (): void {
    [$user, $team] = actingMember();
    $other = Team::factory()->create();
    $user->teams()->attach($other);

    $this->get(route('finance.teams.dashboard.index', ['team' => $team->getRouteKey()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
            ->has('user.teams', 2)
            ->where('user.teams', function (mixed $teams) use ($team, $other): bool {
                $slugs = collect($teams)->pluck('slug');

                return $slugs->contains($team->getRouteKey()) && $slugs->contains($other->getRouteKey());
            }));
});
