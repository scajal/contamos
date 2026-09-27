<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Finance\Models\Team\Transfer;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Feature tests for \App\Modules\Core\Finance\Http\Controllers\Team\MonthlyController.
 */
describe('index', function (): void {
    it('redirects guests to login', function (): void {
        $team = Team::factory()->create();

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertRedirectToRoute('login');
    });

    it('returns 404 when the team does not exist', function (): void {
        actingMember();

        $this->get(route('finance.teams.monthly.index', ['team' => (string) Str::ulid()]))
            ->assertNotFound();
    });

    it('forbids a user who belongs to another team', function (): void {
        actingMember();
        $foreignTeam = Team::factory()->create();

        $this->get(route('finance.teams.monthly.index', ['team' => $foreignTeam->getRouteKey()]))
            ->assertForbidden();
    });

    it('redirects a user with no teams to team creation', function (): void {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $this->actingAs($user);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertRedirectToRoute('identity.teams.create');
    });

    it('switches the current team when the route team differs', function (): void {
        [$user, $currentTeam] = actingMember();
        $otherTeam = Team::factory()->create();
        $user->teams()->attach($otherTeam, ['role' => Role::Owner]);

        expect($user->fresh()?->current_team_id)->toBe($currentTeam->getKey());

        $this->get(route('finance.teams.monthly.index', ['team' => $otherTeam->getRouteKey()]))
            ->assertOk();

        expect($user->fresh()?->current_team_id)->toBe($otherTeam->getKey());
    });

    it('opens on the current month when the url carries no month', function (): void {
        [$user, $team] = actingMember();

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('finance/teams/monthly')
                ->where('month', CarbonImmutable::now()->format('Y-m'))
                ->where('latest_month', CarbonImmutable::now()->format('Y-m'))
                ->where('earliest_month', CarbonImmutable::now()->format('Y-m'))
                ->where('is_locked', false)
                ->where('has_accounts', false));
    });

    it('opens the month the url asks for', function (): void {
        [$user, $team] = actingMember();
        $account = Account::factory()->active()->for($team)->create();
        Transaction::factory()->income()->for($account)->for($team)->create([
            'occurred_at' => CarbonImmutable::now()->subMonths(2),
        ]);
        $month = CarbonImmutable::now()->subMonth()->format('Y-m');

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey(), 'month' => $month]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('month', $month)
                ->where('is_locked', false));
    });

    it('opens a shorter month when today is past its last day', function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-03-31 12:00:00'));
        [$user, $team] = actingMember();

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey(), 'month' => '2026-02']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('month', '2026-02'));
    });

    it('redirects a malformed month to the current month', function (string $month): void {
        [$user, $team] = actingMember();

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey(), 'month' => $month]))
            ->assertRedirectToRoute('finance.teams.monthly.index', ['team' => $team->getRouteKey()]);
    })->with(['2026-13', 'nope', '2026', '2026-01-15']);

    it('redirects a future month to the current month', function (): void {
        [$user, $team] = actingMember();
        $month = CarbonImmutable::now()->addMonth()->format('Y-m');

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey(), 'month' => $month]))
            ->assertRedirectToRoute('finance.teams.monthly.index', ['team' => $team->getRouteKey()]);
    });

    it('reports the first transaction month as the earliest month', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        $account = Account::factory()->active()->for($team)->create();
        Transaction::factory()->income()->for($account)->for($team)->create([
            'occurred_at' => CarbonImmutable::now()->subMonths(9),
        ]);
        Transaction::factory()->income()->for($account)->for($team)->create([
            'occurred_at' => CarbonImmutable::now()->subMonths(2),
        ]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('earliest_month', CarbonImmutable::now()->subMonths(9)->format('Y-m'))
                ->where('latest_month', CarbonImmutable::now()->format('Y-m')));
    });

    it('caps the earliest month of a free team five months back', function (): void {
        [$user, $team] = actingMember();
        $account = Account::factory()->active()->for($team)->create();
        Transaction::factory()->income()->for($account)->for($team)->create([
            'occurred_at' => CarbonImmutable::now()->subMonths(9),
        ]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('earliest_month', CarbonImmutable::now()->subMonths(5)->format('Y-m')));
    });

    it('locks a month older than the free window without hiding the page', function (): void {
        [$user, $team] = actingMember();
        $account = Account::factory()->active()->for($team)->create();
        Transaction::factory()->income()->for($account)->for($team)->create([
            'occurred_at' => CarbonImmutable::now()->subMonths(9),
        ]);
        $month = CarbonImmutable::now()->subMonths(6)->format('Y-m');

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey(), 'month' => $month]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('month', $month)
                ->where('is_locked', true));
    });

    it('opens the same month for a pro team', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        $account = Account::factory()->active()->for($team)->create();
        Transaction::factory()->income()->for($account)->for($team)->create([
            'occurred_at' => CarbonImmutable::now()->subMonths(9),
        ]);
        $month = CarbonImmutable::now()->subMonths(6)->format('Y-m');

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey(), 'month' => $month]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('month', $month)
                ->where('earliest_month', CarbonImmutable::now()->subMonths(9)->format('Y-m'))
                ->where('is_locked', false));
    });

    it('reports whether the team has active accounts', function (): void {
        [$user, $team] = actingMember();
        Account::factory()->inactive()->for($team)->create();

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('has_accounts', false));

        Account::factory()->active()->for($team)->create();

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('has_accounts', true));
    });
});

describe('figures', function (): void {
    it('totals income, expenses and net per currency for the month', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS', 'decimal_places' => 2, 'symbol' => '$']);
        $dollar = Currency::factory()->active()->fiat()->create(['code' => 'USD', 'decimal_places' => 2, 'symbol' => 'US$']);
        $account = Account::factory()->active()->for($team)->create();
        $month = CarbonImmutable::now()->startOfMonth()->addDays(3);

        Transaction::factory()->income()->for($account)->for($peso)->create(['amount' => '100.50', 'occurred_at' => $month]);
        Transaction::factory()->expense()->for($account)->for($peso)->create(['amount' => '30.25', 'occurred_at' => $month]);
        Transaction::factory()->expense()->for($account)->for($dollar)->create(['amount' => '10', 'occurred_at' => $month]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('figures', 2)
                    ->where('figures.0.currency.code', 'ARS')
                    ->where('figures.0.income.amount', fn (mixed $amount): bool => (float) $amount === 100.5)
                    ->where('figures.0.expenses.amount', fn (mixed $amount): bool => (float) $amount === 30.25)
                    ->where('figures.0.net.amount', fn (mixed $amount): bool => (float) $amount === 70.25)
                    ->where('figures.1.currency.code', 'USD')
                    ->where('figures.1.income.amount', fn (mixed $amount): bool => (float) $amount === 0.0)
                    ->where('figures.1.net.amount', fn (mixed $amount): bool => (float) $amount === -10.0)));
    });

    it('leaves out other months and inactive accounts', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        $active = Account::factory()->active()->for($team)->create();
        $inactive = Account::factory()->inactive()->for($team)->create();
        $month = CarbonImmutable::now()->startOfMonth()->addDays(3);

        Transaction::factory()->income()->for($active)->for($peso)->create(['amount' => '100', 'occurred_at' => $month]);
        Transaction::factory()->income()->for($active)->for($peso)->create(['amount' => '500', 'occurred_at' => $month->subMonth()]);
        Transaction::factory()->income()->for($inactive)->for($peso)->create(['amount' => '900', 'occurred_at' => $month]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('figures', 1)
                    ->where('figures.0.income.amount', fn (mixed $amount): bool => (float) $amount === 100.0)));
    });

    it('never mixes in another team', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        $account = Account::factory()->active()->for($team)->create();
        $foreign = Account::factory()->active()->for(Team::factory())->create();
        $month = CarbonImmutable::now()->startOfMonth()->addDays(3);

        Transaction::factory()->income()->for($account)->for($peso)->create(['amount' => '100', 'occurred_at' => $month]);
        Transaction::factory()->income()->for($foreign)->for($peso)->create(['amount' => '900', 'occurred_at' => $month]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('figures', 1)
                    ->where('figures.0.income.amount', fn (mixed $amount): bool => (float) $amount === 100.0)));
    });

    it('keeps transfer legs out of income and expenses and counts their fee as spending', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        $origin = Account::factory()->active()->for($team)->create();
        $destination = Account::factory()->active()->for($team)->create();
        $transfer = Transfer::factory()->for($team)->create();
        $month = CarbonImmutable::now()->startOfMonth()->addDays(3);

        Transaction::factory()->income()->for($origin)->for($peso)->create(['amount' => '100', 'occurred_at' => $month]);
        Transaction::factory()->transferOut()->for($origin)->for($peso)->for($transfer)->create(['amount' => '25', 'occurred_at' => $month]);
        Transaction::factory()->transferIn()->for($destination)->for($peso)->for($transfer)->create(['amount' => '25', 'occurred_at' => $month]);
        Transaction::factory()->expense()->for($origin)->for($peso)->for($transfer)->create(['amount' => '1.50', 'occurred_at' => $month]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->where('figures.0.income.amount', fn (mixed $amount): bool => (float) $amount === 100.0)
                    ->where('figures.0.expenses.amount', fn (mixed $amount): bool => (float) $amount === 1.5)
                    ->where('figures.0.net.amount', fn (mixed $amount): bool => (float) $amount === 98.5)
                    ->where('figures.0.transfers.moved_out', fn (mixed $amount): bool => (float) $amount === 25.0)
                    ->where('figures.0.transfers.moved_in', fn (mixed $amount): bool => (float) $amount === 25.0)));
    });

    it('omits the transfers block for a currency with no transfers that month', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        $account = Account::factory()->active()->for($team)->create();
        $month = CarbonImmutable::now()->startOfMonth()->addDays(3);

        Transaction::factory()->income()->for($account)->for($peso)->create(['amount' => '100', 'occurred_at' => $month]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->where('figures.0.transfers', null)));
    });

    it('compares each total with the month before', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        $account = Account::factory()->active()->for($team)->create();
        $month = CarbonImmutable::now()->startOfMonth()->addDays(3);

        Transaction::factory()->income()->for($account)->for($peso)->create(['amount' => '150', 'occurred_at' => $month]);
        Transaction::factory()->expense()->for($account)->for($peso)->create(['amount' => '50', 'occurred_at' => $month]);
        Transaction::factory()->income()->for($account)->for($peso)->create(['amount' => '100', 'occurred_at' => $month->subMonth()]);
        Transaction::factory()->expense()->for($account)->for($peso)->create(['amount' => '80', 'occurred_at' => $month->subMonth()]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->where('figures.0.income.delta.amount', fn (mixed $amount): bool => (float) $amount === 50.0)
                    ->where('figures.0.income.delta.percentage', fn (mixed $percentage): bool => (float) $percentage === 50.0)
                    ->where('figures.0.expenses.delta.amount', fn (mixed $amount): bool => (float) $amount === -30.0)
                    ->where('figures.0.expenses.delta.percentage', fn (mixed $percentage): bool => (float) $percentage === -37.5)
                    ->where('figures.0.net.delta.amount', fn (mixed $amount): bool => (float) $amount === 80.0)
                    ->where('figures.0.net.delta.percentage', fn (mixed $percentage): bool => (float) $percentage === 400.0)));
    });

    it('drops the percentage when the month before had nothing', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        $account = Account::factory()->active()->for($team)->create();
        $month = CarbonImmutable::now()->startOfMonth()->addDays(3);

        // The month before is inside the window but empty, so
        // there is a delta and no percentage.
        Transaction::factory()->expense()->for($account)->for($peso)->create(['amount' => '40', 'occurred_at' => $month->subMonths(2)]);
        Transaction::factory()->income()->for($account)->for($peso)->create(['amount' => '150', 'occurred_at' => $month]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->where('figures.0.income.delta.amount', fn (mixed $amount): bool => (float) $amount === 150.0)
                    ->where('figures.0.income.delta.percentage', null)));
    });

    it('drops the delta when the month before is outside the plan window', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        $account = Account::factory()->active()->for($team)->create();
        $oldest = CarbonImmutable::now()->startOfMonth()->subMonths(5)->addDays(3);

        Transaction::factory()->income()->for($account)->for($peso)->create(['amount' => '150', 'occurred_at' => $oldest]);

        $this->get(route('finance.teams.monthly.index', [
            'team' => $team->getRouteKey(),
            'month' => $oldest->format('Y-m'),
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('is_locked', false)
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->where('figures.0.income.delta', null)));
    });

    it('sends no figures at all for a locked month', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        $account = Account::factory()->active()->for($team)->create();
        $old = CarbonImmutable::now()->startOfMonth()->subMonths(8)->addDays(3);

        Transaction::factory()->income()->for($account)->for($peso)->create(['amount' => '150', 'occurred_at' => $old]);

        $this->get(route('finance.teams.monthly.index', [
            'team' => $team->getRouteKey(),
            'month' => $old->format('Y-m'),
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('is_locked', true)
                ->where('figures', []));
    });

    it('reports zero totals for a month with no activity', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        $account = Account::factory()->active()->for($team)->create();

        Transaction::factory()->income()->for($account)->for($peso)->create([
            'amount' => '150',
            'occurred_at' => CarbonImmutable::now()->startOfMonth()->subMonths(2)->addDays(3),
        ]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('figures', 1)
                    ->where('figures.0.income.amount', fn (mixed $amount): bool => (float) $amount === 0.0)
                    ->where('figures.0.expenses.amount', fn (mixed $amount): bool => (float) $amount === 0.0)
                    ->where('figures.0.net.amount', fn (mixed $amount): bool => (float) $amount === 0.0)
                    ->where('figures.0.transfers', null)));
    });
});

describe('tags', function (): void {
    it('breaks the month down by tag, biggest spender first', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        $account = Account::factory()->active()->for($team)->create();
        $rent = Tag::factory()->active()->for($team)->create(['name' => 'Rent']);
        $food = Tag::factory()->active()->for($team)->create(['name' => 'Food']);
        $month = CarbonImmutable::now()->startOfMonth()->addDays(3);

        $small = Transaction::factory()->expense()->for($account)->for($peso)->create(['amount' => '40', 'occurred_at' => $month]);
        $small->attachTags([$food->getKey()]);

        $big = Transaction::factory()->expense()->for($account)->for($peso)->create(['amount' => '900', 'occurred_at' => $month]);
        $big->attachTags([$rent->getKey()]);

        $earned = Transaction::factory()->income()->for($account)->for($peso)->create(['amount' => '60', 'occurred_at' => $month]);
        $earned->attachTags([$food->getKey()]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('figures.0.tags', 2)
                    ->where('figures.0.tags.0.name', 'Rent')
                    ->where('figures.0.tags.0.expenses', fn (mixed $amount): bool => (float) $amount === 900.0)
                    ->where('figures.0.tags.1.name', 'Food')
                    ->where('figures.0.tags.1.income', fn (mixed $amount): bool => (float) $amount === 60.0)
                    ->where('figures.0.tags.1.expenses', fn (mixed $amount): bool => (float) $amount === 40.0)
                    ->where('figures.0.tags.1.net', fn (mixed $amount): bool => (float) $amount === 20.0)));
    });

    it('counts a transaction in full under each of its tags', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        $account = Account::factory()->active()->for($team)->create();
        $travel = Tag::factory()->active()->for($team)->create(['name' => 'Travel']);
        $work = Tag::factory()->active()->for($team)->create(['name' => 'Work']);
        $month = CarbonImmutable::now()->startOfMonth()->addDays(3);

        $flight = Transaction::factory()->expense()->for($account)->for($peso)->create(['amount' => '300', 'occurred_at' => $month]);
        $flight->attachTags([$travel->getKey(), $work->getKey()]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('figures.0.tags', 2)
                    ->where('figures.0.tags.0.expenses', fn (mixed $amount): bool => (float) $amount === 300.0)
                    ->where('figures.0.tags.1.expenses', fn (mixed $amount): bool => (float) $amount === 300.0)
                    ->where('figures.0.expenses.amount', fn (mixed $amount): bool => (float) $amount === 300.0)));
    });

    it('settles a tie on the tag name', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        $account = Account::factory()->active()->for($team)->create();
        $beta = Tag::factory()->active()->for($team)->create(['name' => 'Beta']);
        $alpha = Tag::factory()->active()->for($team)->create(['name' => 'Alpha']);
        $month = CarbonImmutable::now()->startOfMonth()->addDays(3);

        foreach ([$beta, $alpha] as $tag) {
            $spent = Transaction::factory()->expense()->for($account)->for($peso)->create(['amount' => '50', 'occurred_at' => $month]);
            $spent->attachTags([$tag->getKey()]);
        }

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->where('figures.0.tags.0.name', 'Alpha')
                    ->where('figures.0.tags.1.name', 'Beta')));
    });

    it('keeps the untagged row last even when it spent the most', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        $account = Account::factory()->active()->for($team)->create();
        $food = Tag::factory()->active()->for($team)->create(['name' => 'Food']);
        $month = CarbonImmutable::now()->startOfMonth()->addDays(3);

        $tagged = Transaction::factory()->expense()->for($account)->for($peso)->create(['amount' => '40', 'occurred_at' => $month]);
        $tagged->attachTags([$food->getKey()]);

        Transaction::factory()->expense()->for($account)->for($peso)->create(['amount' => '900', 'occurred_at' => $month]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('figures.0.tags', 2)
                    ->where('figures.0.tags.0.name', 'Food')
                    ->where('figures.0.tags.1.name', null)
                    ->where('figures.0.tags.1.id', null)
                    ->where('figures.0.tags.1.expenses', fn (mixed $amount): bool => (float) $amount === 900.0)));
    });

    it('keeps a deactivated tag that was used that month and drops one that was not', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        $account = Account::factory()->active()->for($team)->create();
        $retired = Tag::factory()->inactive()->for($team)->create(['name' => 'Retired']);
        Tag::factory()->inactive()->for($team)->create(['name' => 'Unused']);
        $month = CarbonImmutable::now()->startOfMonth()->addDays(3);

        $spent = Transaction::factory()->expense()->for($account)->for($peso)->create(['amount' => '70', 'occurred_at' => $month]);
        $spent->attachTags([$retired->getKey()]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('figures.0.tags', 1)
                    ->where('figures.0.tags.0.name', 'Retired')));
    });

    it('keeps transfer legs out of the tag rows and counts their fee', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        $origin = Account::factory()->active()->for($team)->create();
        $destination = Account::factory()->active()->for($team)->create();
        $transfer = Transfer::factory()->for($team)->create();
        $bank = Tag::factory()->active()->for($team)->create(['name' => 'Bank']);
        $month = CarbonImmutable::now()->startOfMonth()->addDays(3);

        $out = Transaction::factory()->transferOut()->for($origin)->for($peso)->for($transfer)->create(['amount' => '25', 'occurred_at' => $month]);
        $out->attachTags([$bank->getKey()]);
        $in = Transaction::factory()->transferIn()->for($destination)->for($peso)->for($transfer)->create(['amount' => '25', 'occurred_at' => $month]);
        $in->attachTags([$bank->getKey()]);
        $fee = Transaction::factory()->expense()->for($origin)->for($peso)->for($transfer)->create(['amount' => '1.50', 'occurred_at' => $month]);
        $fee->attachTags([$bank->getKey()]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('figures.0.tags', 1)
                    ->where('figures.0.tags.0.name', 'Bank')
                    ->where('figures.0.tags.0.expenses', fn (mixed $amount): bool => (float) $amount === 1.5)
                    ->where('figures.0.tags.0.income', fn (mixed $amount): bool => (float) $amount === 0.0)));
    });

    it('never shows another team its tags', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        $account = Account::factory()->active()->for($team)->create();
        $mine = Tag::factory()->active()->for($team)->create(['name' => 'Mine']);
        $foreignTeam = Team::factory()->create();
        $foreignAccount = Account::factory()->active()->for($foreignTeam)->create();
        $theirs = Tag::factory()->active()->for($foreignTeam)->create(['name' => 'Theirs']);
        $month = CarbonImmutable::now()->startOfMonth()->addDays(3);

        $spent = Transaction::factory()->expense()->for($account)->for($peso)->create(['amount' => '10', 'occurred_at' => $month]);
        $spent->attachTags([$mine->getKey()]);
        $foreign = Transaction::factory()->expense()->for($foreignAccount)->for($peso)->create(['amount' => '900', 'occurred_at' => $month]);
        $foreign->attachTags([$theirs->getKey()]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('figures.0.tags', 1)
                    ->where('figures.0.tags.0.name', 'Mine')));
    });

    it('sends an empty tag list for a month with no activity', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        $account = Account::factory()->active()->for($team)->create();

        Transaction::factory()->income()->for($account)->for($peso)->create([
            'amount' => '150',
            'occurred_at' => CarbonImmutable::now()->startOfMonth()->subMonths(2)->addDays(3),
        ]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('figures.0.tags', 0)));
    });
});

describe('categories', function (): void {
    it('breaks the month down by category, summing to the month totals', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        $account = Account::factory()->active()->for($team)->create();
        $month = CarbonImmutable::now()->startOfMonth()->addDays(3);

        Transaction::factory()->expense()->for($account)->for($peso)->create(['amount' => '900', 'category' => Category::Housing, 'occurred_at' => $month]);
        Transaction::factory()->expense()->for($account)->for($peso)->create(['amount' => '40', 'category' => Category::Groceries, 'occurred_at' => $month]);
        Transaction::factory()->expense()->for($account)->for($peso)->create(['amount' => '60', 'category' => Category::Groceries, 'occurred_at' => $month]);
        Transaction::factory()->income()->for($account)->for($peso)->create(['amount' => '1200', 'category' => Category::Salary, 'occurred_at' => $month]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('figures.0.categories', 3)
                    ->where('figures.0.expenses.amount', fn (mixed $amount): bool => (float) $amount === 1000.0)
                    ->where('figures.0.categories', fn (Collection $rows): bool => $rows
                        ->sum(fn (array $row): float => (float) $row['expenses']) === 1000.0)
                    ->where('figures.0.categories', fn (Collection $rows): bool => $rows
                        ->sum(fn (array $row): float => (float) $row['income']) === 1200.0)
                    ->where('figures.0.categories', fn (Collection $rows): bool => (float) ($rows
                        ->firstWhere('category', Category::Groceries->value)['net'] ?? 0) === -100.0)));
    });

    it('orders the rows the way the category list reads, with other last', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        $account = Account::factory()->active()->for($team)->create();
        $month = CarbonImmutable::now()->startOfMonth()->addDays(3);

        // Recorded biggest first, so an order by amount would
        // hand them back exactly the other way around.
        Transaction::factory()->expense()->for($account)->for($peso)->create(['amount' => '900', 'category' => Category::Other, 'occurred_at' => $month]);
        Transaction::factory()->expense()->for($account)->for($peso)->create(['amount' => '400', 'category' => Category::Groceries, 'occurred_at' => $month]);
        Transaction::factory()->expense()->for($account)->for($peso)->create(['amount' => '10', 'category' => Category::Housing, 'occurred_at' => $month]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->where('figures.0.categories.0.category', Category::Housing->value)
                    ->where('figures.0.categories.1.category', Category::Groceries->value)
                    ->where('figures.0.categories.2.category', Category::Other->value)));
    });

    it('reads money filed under no category as other', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        $account = Account::factory()->active()->for($team)->create();
        $month = CarbonImmutable::now()->startOfMonth()->addDays(3);

        Transaction::factory()->expense()->for($account)->for($peso)->create(['amount' => '30', 'category' => null, 'occurred_at' => $month]);
        Transaction::factory()->expense()->for($account)->for($peso)->create(['amount' => '20', 'category' => Category::Other, 'occurred_at' => $month]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('figures.0.categories', 1)
                    ->where('figures.0.categories.0.category', Category::Other->value)
                    ->where('figures.0.categories.0.expenses', fn (mixed $amount): bool => (float) $amount === 50.0)));
    });

    it('keeps transfer legs and opening balances out of the category rows', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        $origin = Account::factory()->active()->for($team)->create();
        $destination = Account::factory()->active()->for($team)->create();
        $transfer = Transfer::factory()->for($team)->create();
        $month = CarbonImmutable::now()->startOfMonth()->addDays(3);

        Transaction::factory()->transferOut()->for($origin)->for($peso)->for($transfer)->create(['amount' => '25', 'occurred_at' => $month]);
        Transaction::factory()->transferIn()->for($destination)->for($peso)->for($transfer)->create(['amount' => '25', 'occurred_at' => $month]);
        Transaction::factory()->openingBalance()->for($origin)->for($peso)->create(['amount' => '700', 'occurred_at' => $month]);
        Transaction::factory()->expense()->for($origin)->for($peso)->for($transfer)->create(['amount' => '1.50', 'category' => Category::TaxesAndFees, 'occurred_at' => $month]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('figures.0.categories', 1)
                    ->where('figures.0.categories.0.category', Category::TaxesAndFees->value)
                    ->where('figures.0.categories.0.expenses', fn (mixed $amount): bool => (float) $amount === 1.5)));
    });

    it('leaves out other months and inactive accounts', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        $active = Account::factory()->active()->for($team)->create();
        $inactive = Account::factory()->inactive()->for($team)->create();
        $month = CarbonImmutable::now()->startOfMonth()->addDays(3);

        Transaction::factory()->expense()->for($active)->for($peso)->create(['amount' => '100', 'category' => Category::Groceries, 'occurred_at' => $month]);
        Transaction::factory()->expense()->for($active)->for($peso)->create(['amount' => '500', 'category' => Category::Housing, 'occurred_at' => $month->subMonth()]);
        Transaction::factory()->expense()->for($inactive)->for($peso)->create(['amount' => '900', 'category' => Category::Transport, 'occurred_at' => $month]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('figures.0.categories', 1)
                    ->where('figures.0.categories.0.category', Category::Groceries->value)
                    ->where('figures.0.categories.0.expenses', fn (mixed $amount): bool => (float) $amount === 100.0)));
    });

    it('breaks each currency down on its own', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        $dollar = Currency::factory()->active()->fiat()->create(['code' => 'USD']);
        $account = Account::factory()->active()->for($team)->create();
        $month = CarbonImmutable::now()->startOfMonth()->addDays(3);

        Transaction::factory()->expense()->for($account)->for($peso)->create(['amount' => '100', 'category' => Category::Groceries, 'occurred_at' => $month]);
        Transaction::factory()->expense()->for($account)->for($dollar)->create(['amount' => '20', 'category' => Category::Transport, 'occurred_at' => $month]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('figures', 2)
                    ->where('figures.0.currency.code', 'ARS')
                    ->has('figures.0.categories', 1)
                    ->where('figures.0.categories.0.category', Category::Groceries->value)
                    ->where('figures.1.currency.code', 'USD')
                    ->has('figures.1.categories', 1)
                    ->where('figures.1.categories.0.category', Category::Transport->value)));
    });

    it('sends an empty category list for a month with no activity', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        $account = Account::factory()->active()->for($team)->create();

        Transaction::factory()->income()->for($account)->for($peso)->create([
            'amount' => '150',
            'occurred_at' => CarbonImmutable::now()->startOfMonth()->subMonths(2)->addDays(3),
        ]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('figures.0.categories', 0)));
    });
});

describe('trend', function (): void {
    it('covers the six months ending on the selected month, zero filling the quiet ones', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        $account = Account::factory()->active()->for($team)->create();
        $month = CarbonImmutable::now()->startOfMonth()->addDays(3);

        Transaction::factory()->income()->for($account)->for($peso)->create(['amount' => '200', 'occurred_at' => $month->subMonths(9)]);
        Transaction::factory()->income()->for($account)->for($peso)->create(['amount' => '100', 'occurred_at' => $month->subMonths(3)]);
        Transaction::factory()->expense()->for($account)->for($peso)->create(['amount' => '25', 'occurred_at' => $month->subMonths(3)]);
        Transaction::factory()->income()->for($account)->for($peso)->create(['amount' => '400', 'occurred_at' => $month]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('figures.0.trend', 6)
                    ->where('figures.0.trend.0.month', CarbonImmutable::now()->subMonths(5)->format('Y-m'))
                    ->where('figures.0.trend.0.income', fn (mixed $amount): bool => (float) $amount === 0.0)
                    ->where('figures.0.trend.2.month', CarbonImmutable::now()->subMonths(3)->format('Y-m'))
                    ->where('figures.0.trend.2.income', fn (mixed $amount): bool => (float) $amount === 100.0)
                    ->where('figures.0.trend.2.expenses', fn (mixed $amount): bool => (float) $amount === 25.0)
                    ->where('figures.0.trend.2.net', fn (mixed $amount): bool => (float) $amount === 75.0)
                    ->where('figures.0.trend.5.month', CarbonImmutable::now()->format('Y-m'))
                    ->where('figures.0.trend.5.income', fn (mixed $amount): bool => (float) $amount === 400.0)));
    });

    it('stops the trend at the oldest month the plan opens', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        $account = Account::factory()->active()->for($team)->create();
        $oldest = CarbonImmutable::now()->startOfMonth()->subMonths(5)->addDays(3);

        Transaction::factory()->income()->for($account)->for($peso)->create(['amount' => '150', 'occurred_at' => $oldest]);

        $this->get(route('finance.teams.monthly.index', [
            'team' => $team->getRouteKey(),
            'month' => $oldest->format('Y-m'),
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('figures.0.trend', 1)
                    ->where('figures.0.trend.0.month', $oldest->format('Y-m'))
                    ->where('figures.0.trend.0.income', fn (mixed $amount): bool => (float) $amount === 150.0)));
    });

    it('keeps other months, inactive accounts and transfer legs out of the trend', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        $origin = Account::factory()->active()->for($team)->create();
        $destination = Account::factory()->active()->for($team)->create();
        $inactive = Account::factory()->inactive()->for($team)->create();
        $transfer = Transfer::factory()->for($team)->create();
        $month = CarbonImmutable::now()->startOfMonth()->addDays(3);

        Transaction::factory()->income()->for($origin)->for($peso)->create(['amount' => '100', 'occurred_at' => $month->subMonth()]);
        Transaction::factory()->income()->for($inactive)->for($peso)->create(['amount' => '900', 'occurred_at' => $month->subMonth()]);
        Transaction::factory()->transferOut()->for($origin)->for($peso)->for($transfer)->create(['amount' => '25', 'occurred_at' => $month->subMonth()]);
        Transaction::factory()->transferIn()->for($destination)->for($peso)->for($transfer)->create(['amount' => '25', 'occurred_at' => $month->subMonth()]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('figures.0.trend', 2)
                    ->where('figures.0.trend.0.income', fn (mixed $amount): bool => (float) $amount === 100.0)
                    ->where('figures.0.trend.0.expenses', fn (mixed $amount): bool => (float) $amount === 0.0)
                    ->where('figures.0.trend.1.income', fn (mixed $amount): bool => (float) $amount === 0.0)));
    });
});

describe('budgets', function (): void {
    /**
     * Create a pro team holding one currency on an active account,
     * with a tag to budget and spend against.
     *
     * @return array{0: \App\Modules\Core\Identity\Models\Team, 1: \App\Modules\Core\Catalog\Models\Currency, 2: \App\Modules\Core\Finance\Models\Team\Account, 3: \App\Modules\Core\Finance\Models\Team\Tag}
     */
    $budgetingTeam = function (bool $pro = true): array {
        [$user, $team] = actingMember();

        if ($pro) {
            subscribeToPro($team);
        }

        $currency = Currency::factory()->active()->fiat()->create(['code' => 'ARS', 'decimal_places' => 2]);
        $account = Account::factory()->active()->for($team)->create();
        $account->currencies()->attach($currency, ['balance' => '0']);
        $tag = Tag::factory()->active()->for($team)->create(['name' => 'Food']);

        return [$team, $currency, $account, $tag];
    };

    it('reports what the month made of a budgeted tag', function () use ($budgetingTeam): void {
        [$team, $currency, $account, $tag] = $budgetingTeam();
        BudgetPeriod::factory()->running()->forTag($tag)->for($currency)->create(['amount' => '500']);

        $spent = Transaction::factory()->expense()->for($account)->for($currency)->create([
            'amount' => '120',
            'occurred_at' => CarbonImmutable::now()->startOfMonth()->addDay(),
        ]);
        $spent->attachTags([$tag->getKey()]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('can_budget', true)
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->where('figures.0.tags.0.budget.amount', fn (mixed $value): bool => (float) $value === 500.0)
                    ->where('figures.0.tags.0.budget.spent', fn (mixed $value): bool => (float) $value === 120.0)
                    ->where('figures.0.tags.0.budget.income', fn (mixed $value): bool => (float) $value === 0.0)
                    ->where('figures.0.tags.0.budget.remaining', fn (mixed $value): bool => (float) $value === 380.0)
                    ->where('figures.0.tags.0.budget.percentage', fn (mixed $value): bool => (float) $value === 24.0)));
    });

    it('carries the warning threshold of a budgeted tag', function () use ($budgetingTeam): void {
        [$team, $currency, $account, $tag] = $budgetingTeam();
        BudgetPeriod::factory()->running()->withWarningThreshold(80)->forTag($tag)->for($currency)->create(['amount' => '500']);

        $spent = Transaction::factory()->expense()->for($account)->for($currency)->create([
            'amount' => '120',
            'occurred_at' => CarbonImmutable::now()->startOfMonth()->addDay(),
        ]);
        $spent->attachTags([$tag->getKey()]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->where('figures.0.tags.0.budget.warning_threshold', 80)));
    });

    it('enlarges the pot with income tagged into the budget', function () use ($budgetingTeam): void {
        [$team, $currency, $account, $tag] = $budgetingTeam();
        BudgetPeriod::factory()->running()->forTag($tag)->for($currency)->create(['amount' => '500']);
        $month = CarbonImmutable::now()->startOfMonth()->addDay();

        $earned = Transaction::factory()->income()->for($account)->for($currency)->create(['amount' => '100', 'occurred_at' => $month]);
        $earned->attachTags([$tag->getKey()]);

        $spent = Transaction::factory()->expense()->for($account)->for($currency)->create(['amount' => '300', 'occurred_at' => $month]);
        $spent->attachTags([$tag->getKey()]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->where('figures.0.tags.0.budget.income', fn (mixed $value): bool => (float) $value === 100.0)
                    ->where('figures.0.tags.0.budget.remaining', fn (mixed $value): bool => (float) $value === 300.0)
                    ->where('figures.0.tags.0.budget.percentage', fn (mixed $value): bool => (float) $value === 50.0)));
    });

    it('reports a tag over its budget with a negative remaining', function () use ($budgetingTeam): void {
        [$team, $currency, $account, $tag] = $budgetingTeam();
        BudgetPeriod::factory()->running()->forTag($tag)->for($currency)->create(['amount' => '100']);

        $spent = Transaction::factory()->expense()->for($account)->for($currency)->create([
            'amount' => '150',
            'occurred_at' => CarbonImmutable::now()->startOfMonth()->addDay(),
        ]);
        $spent->attachTags([$tag->getKey()]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->where('figures.0.tags.0.budget.remaining', fn (mixed $value): bool => (float) $value === -50.0)
                    ->where('figures.0.tags.0.budget.percentage', fn (mixed $value): bool => (float) $value === 150.0)));
    });

    it('leaves an unbudgeted tag and the untagged row without a budget', function () use ($budgetingTeam): void {
        [$team, $currency, $account, $tag] = $budgetingTeam();
        $month = CarbonImmutable::now()->startOfMonth()->addDay();

        $tagged = Transaction::factory()->expense()->for($account)->for($currency)->create(['amount' => '40', 'occurred_at' => $month]);
        $tagged->attachTags([$tag->getKey()]);

        Transaction::factory()->expense()->for($account)->for($currency)->create(['amount' => '10', 'occurred_at' => $month]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('figures.0.tags', 2)
                    ->where('figures.0.tags.0.budget', null)
                    ->where('figures.0.tags.1.name', null)
                    ->where('figures.0.tags.1.budget', null)));
    });

    it('reports a budgeted tag the month has no figures for', function () use ($budgetingTeam): void {
        [$team, $currency, $account, $tag] = $budgetingTeam();
        $quiet = Tag::factory()->active()->for($team)->create(['name' => 'Zzz']);
        BudgetPeriod::factory()->running()->forTag($quiet)->for($currency)->create(['amount' => '200']);

        $spent = Transaction::factory()->expense()->for($account)->for($currency)->create([
            'amount' => '40',
            'occurred_at' => CarbonImmutable::now()->startOfMonth()->addDay(),
        ]);
        $spent->attachTags([$tag->getKey()]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('figures.0.tags', 2)
                    ->where('figures.0.tags.0.name', 'Food')
                    ->where('figures.0.tags.1.name', 'Zzz')
                    ->where('figures.0.tags.1.budget.spent', fn (mixed $value): bool => (float) $value === 0.0)
                    ->where('figures.0.tags.1.budget.amount', fn (mixed $value): bool => (float) $value === 200.0)));
    });

    it('keeps a past month on the amount that was in force then', function () use ($budgetingTeam): void {
        [$team, $currency, $account, $tag] = $budgetingTeam();
        $past = CarbonImmutable::now()->startOfMonth()->subMonth();

        BudgetPeriod::factory()->forTag($tag)->for($currency)->create([
            'amount' => '300',
            'from_month' => $past->subMonths(2),
            'until_month' => $past,
        ]);
        BudgetPeriod::factory()->running()->forTag($tag)->for($currency)->create(['amount' => '900']);

        $spent = Transaction::factory()->expense()->for($account)->for($currency)->create([
            'amount' => '150',
            'occurred_at' => $past->addDay(),
        ]);
        $spent->attachTags([$tag->getKey()]);

        $this->get(route('finance.teams.monthly.index', [
            'team' => $team->getRouteKey(),
            'month' => $past->format('Y-m'),
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->where('figures.0.tags.0.budget.amount', fn (mixed $value): bool => (float) $value === 300.0)
                    ->where('figures.0.tags.0.budget.remaining', fn (mixed $value): bool => (float) $value === 150.0)));
    });

    it('keeps applying a budget on a deactivated tag', function () use ($budgetingTeam): void {
        [$team, $currency, $account, $tag] = $budgetingTeam();
        BudgetPeriod::factory()->running()->forTag($tag)->for($currency)->create(['amount' => '500']);

        $spent = Transaction::factory()->expense()->for($account)->for($currency)->create([
            'amount' => '120',
            'occurred_at' => CarbonImmutable::now()->startOfMonth()->addDay(),
        ]);
        $spent->attachTags([$tag->getKey()]);

        $tag->deactivate();

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->where('figures.0.tags.0.budget.amount', fn (mixed $value): bool => (float) $value === 500.0)));
    });

    it('reports what the month made of a budgeted category', function () use ($budgetingTeam): void {
        [$team, $currency, $account] = $budgetingTeam();
        BudgetPeriod::factory()->running()->forCategory(Category::Groceries)->for($team)->for($currency)->create(['amount' => '500']);

        Transaction::factory()->expense()->for($account)->for($currency)->create([
            'amount' => '120',
            'category' => Category::Groceries,
            'occurred_at' => CarbonImmutable::now()->startOfMonth()->addDay(),
        ]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('can_budget', true)
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->where('figures.0.categories.0.category', Category::Groceries->value)
                    ->where('figures.0.categories.0.budget.amount', fn (mixed $value): bool => (float) $value === 500.0)
                    ->where('figures.0.categories.0.budget.spent', fn (mixed $value): bool => (float) $value === 120.0)
                    ->where('figures.0.categories.0.budget.income', fn (mixed $value): bool => (float) $value === 0.0)
                    ->where('figures.0.categories.0.budget.remaining', fn (mixed $value): bool => (float) $value === 380.0)
                    ->where('figures.0.categories.0.budget.percentage', fn (mixed $value): bool => (float) $value === 24.0)));
    });

    it('lists the sub-budgets of a budgeted category under its row', function () use ($budgetingTeam): void {
        [$team, $currency, $account, $tag] = $budgetingTeam();
        $parent = BudgetPeriod::factory()->running()->forCategory(Category::Groceries)->for($team)->for($currency)->create(['amount' => '500']);
        BudgetPeriod::factory()->within($parent)->running()->create(['amount' => '200', 'tag_id' => $tag->getKey()]);
        $day = CarbonImmutable::now()->startOfMonth()->addDay();

        Transaction::factory()->expense()->for($account)->for($currency)->create([
            'amount' => '120',
            'category' => Category::Groceries,
            'occurred_at' => $day,
        ])->attachTags([$tag->getKey()]);
        Transaction::factory()->expense()->for($account)->for($currency)->create([
            'amount' => '80',
            'category' => Category::Transport,
            'occurred_at' => $day,
        ])->attachTags([$tag->getKey()]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->where('figures.0.categories.0.category', Category::Groceries->value)
                    ->has('figures.0.categories.0.parts', 1)
                    ->where('figures.0.categories.0.parts.0.id', $tag->getKey())
                    ->where('figures.0.categories.0.parts.0.name', 'Food')
                    ->where('figures.0.categories.0.parts.0.budget.amount', fn (mixed $value): bool => (float) $value === 200.0)
                    ->where('figures.0.categories.0.parts.0.budget.spent', fn (mixed $value): bool => (float) $value === 120.0)
                    ->where('figures.0.categories.0.parts.0.budget.remaining', fn (mixed $value): bool => (float) $value === 80.0)
                    ->where('figures.0.categories.1.category', Category::Transport->value)
                    ->where('figures.0.categories.1.parts', [])
                    // The tag breakdown keeps counting the tag across categories.
                    ->where('figures.0.tags.0.expenses', fn (mixed $value): bool => (float) $value === 200.0)));
    });

    it('lists the sub-budgets of a budgeted category the month has no figures for', function () use ($budgetingTeam): void {
        [$team, $currency, $account] = $budgetingTeam();
        $parent = BudgetPeriod::factory()->running()->forCategory(Category::Groceries)->for($team)->for($currency)->create(['amount' => '500']);
        BudgetPeriod::factory()->within($parent)->running()->create(['amount' => '200']);

        // The month has movements, just none under the budgeted category.
        Transaction::factory()->expense()->for($account)->for($currency)->create([
            'amount' => '40',
            'category' => Category::Housing,
            'occurred_at' => CarbonImmutable::now()->startOfMonth()->addDay(),
        ]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    // Housing reads first, so the quiet Food budget is the second row.
                    ->where('figures.0.categories.1.category', Category::Groceries->value)
                    ->has('figures.0.categories.1.parts', 1)
                    ->where('figures.0.categories.1.parts.0.budget.spent', fn (mixed $value): bool => (float) $value === 0.0)));
    });

    it('leaves a category row without a budget the tag budget follows', function () use ($budgetingTeam): void {
        [$team, $currency, $account, $tag] = $budgetingTeam();
        BudgetPeriod::factory()->running()->forTag($tag)->for($currency)->create(['amount' => '500']);

        $spent = Transaction::factory()->expense()->for($account)->for($currency)->create([
            'amount' => '120',
            'category' => Category::Groceries,
            'occurred_at' => CarbonImmutable::now()->startOfMonth()->addDay(),
        ]);
        $spent->attachTags([$tag->getKey()]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->where('figures.0.categories.0.budget', null)
                    ->where('figures.0.tags.0.budget.amount', fn (mixed $value): bool => (float) $value === 500.0)));
    });

    it('reports a budgeted category the month has no figures for', function () use ($budgetingTeam): void {
        [$team, $currency, $account] = $budgetingTeam();
        BudgetPeriod::factory()->running()->forCategory(Category::Transport)->for($team)->for($currency)->create(['amount' => '200']);

        Transaction::factory()->expense()->for($account)->for($currency)->create([
            'amount' => '40',
            'category' => Category::Housing,
            'occurred_at' => CarbonImmutable::now()->startOfMonth()->addDay(),
        ]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('figures.0.categories', 2)
                    ->where('figures.0.categories.0.category', Category::Housing->value)
                    ->where('figures.0.categories.1.category', Category::Transport->value)
                    ->where('figures.0.categories.1.budget.spent', fn (mixed $value): bool => (float) $value === 0.0)
                    ->where('figures.0.categories.1.budget.amount', fn (mixed $value): bool => (float) $value === 200.0)));
    });

    it('omits the category budget blocks for a free team', function () use ($budgetingTeam): void {
        [$team, $currency, $account] = $budgetingTeam(false);
        BudgetPeriod::factory()->running()->forCategory(Category::Groceries)->for($team)->for($currency)->create(['amount' => '500']);

        Transaction::factory()->expense()->for($account)->for($currency)->create([
            'amount' => '120',
            'category' => Category::Groceries,
            'occurred_at' => CarbonImmutable::now()->startOfMonth()->addDay(),
        ]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('can_budget', false)
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('figures.0.categories', 1)
                    ->where('figures.0.categories.0.budget', null)));
    });

    it('omits the budget blocks and flags the upsell for a free team', function () use ($budgetingTeam): void {
        [$team, $currency, $account, $tag] = $budgetingTeam(false);
        BudgetPeriod::factory()->running()->forTag($tag)->for($currency)->create(['amount' => '500']);

        $spent = Transaction::factory()->expense()->for($account)->for($currency)->create([
            'amount' => '120',
            'occurred_at' => CarbonImmutable::now()->startOfMonth()->addDay(),
        ]);
        $spent->attachTags([$tag->getKey()]);

        $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('can_budget', false)
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('figures.0.tags', 1)
                    ->where('figures.0.tags.0.budget', null)));
    });
});
