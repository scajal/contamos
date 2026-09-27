<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetPeriodStatus;
use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetTargetKind;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Finance\Notifications\Team\BudgetAlertNotification;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Feature tests for \App\Modules\Core\Finance\Http\Controllers\Team\BudgetController.
 */

/**
 * Create a pro \App\Modules\Core\Identity\Models\Team holding one
 * \App\Modules\Core\Catalog\Models\Currency on an active account,
 * with a \App\Modules\Core\Finance\Models\Team\Tag to budget.
 *
 * @return array{0: \App\Modules\Core\Identity\Models\User, 1: \App\Modules\Core\Identity\Models\Team, 2: \App\Modules\Core\Catalog\Models\Currency, 3: \App\Modules\Core\Finance\Models\Team\Tag, 4: \App\Modules\Core\Finance\Models\Team\Account}
 */
function budgetingTeam(): array
{
    [$user, $team] = actingMember();
    subscribeToPro($team);

    $currency = Currency::factory()->active()->fiat()->create(['code' => 'ARS', 'decimal_places' => 2]);
    $account = Account::factory()->active()->for($team)->create();
    $account->currencies()->attach($currency, ['balance' => '0']);
    $tag = Tag::factory()->active()->for($team)->create(['name' => 'Food']);

    return [$user, $team, $currency, $tag, $account];
}

/**
 * Valid payload for storing a
 * \App\Modules\Core\Finance\Models\Team\BudgetPeriod.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function budgetPayload(Tag $tag, Currency $currency, array $overrides = []): array
{
    return [
        'amount' => '500.00',
        'currency_id' => $currency->getKey(),
        'from_month' => CarbonImmutable::now()->format('Y-m'),
        'tag_id' => $tag->getKey(),
        'target_kind' => BudgetTargetKind::Tag->value,
        'until_month' => null,
        ...$overrides,
    ];
}

/**
 * Valid payload for storing a category
 * \App\Modules\Core\Finance\Models\Team\BudgetPeriod.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function categoryBudgetPayload(Category $category, Currency $currency, array $overrides = []): array
{
    return [
        'amount' => '8000.00',
        'category' => $category->value,
        'currency_id' => $currency->getKey(),
        'from_month' => CarbonImmutable::now()->format('Y-m'),
        'target_kind' => BudgetTargetKind::Category->value,
        'until_month' => null,
        ...$overrides,
    ];
}

describe('index', function (): void {
    it('renders a deferred listing of the budgets', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        BudgetPeriod::factory()->running()->forTag($tag)->for($currency)->create(['amount' => '500']);

        $this->get(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('finance/teams/budgets/index')
                ->where('is_locked', false)
                ->has('currencies', 1)
                ->has('tags', 1)
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->has('budgets.data', 1)
                    ->where('budgets.data.0.target.tag.name', 'Food')
                    ->where('budgets.data.0.currency.code', 'ARS')
                    ->where('budgets.data.0.status', BudgetPeriodStatus::Running->value)));
    });

    it('reports the current month progress of the running budget', function (): void {
        [$user, $team, $currency, $tag, $account] = budgetingTeam();
        BudgetPeriod::factory()->running()->forTag($tag)->for($currency)->create(['amount' => '500']);

        $spent = Transaction::factory()->expense()->for($account)->for($currency)->create([
            'amount' => '200',
            'occurred_at' => CarbonImmutable::now()->startOfMonth()->addDay(),
        ]);
        $spent->attachTags([$tag->getKey()]);

        $this->get(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->where('budgets.data.0.progress.spent', fn (mixed $value): bool => (float) $value === 200.0)
                    ->where('budgets.data.0.progress.remaining', fn (mixed $value): bool => (float) $value === 300.0)
                    ->where('budgets.data.0.progress.percentage', fn (mixed $value): bool => (float) $value === 40.0)));
    });

    it('reports the warning threshold on the row and its progress', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        BudgetPeriod::factory()->running()->withWarningThreshold(80)->forTag($tag)->for($currency)->create();

        $this->get(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->where('budgets.data.0.warning_threshold', 80)
                    ->where('budgets.data.0.progress.warning_threshold', 80)));
    });

    it('omits the percentage when the budget pot is empty', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        BudgetPeriod::factory()->running()->forTag($tag)->for($currency)->create(['amount' => '0']);

        $this->get(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->where('budgets.data.0.progress.percentage', null)));
    });

    it('reports a scheduled, a paused and an ended budget', function (): void {
        [$user, $team, $currency] = budgetingTeam();
        $later = Tag::factory()->active()->for($team)->create(['name' => 'Aaa']);
        $stopped = Tag::factory()->active()->for($team)->create(['name' => 'Bbb']);
        $objective = Tag::factory()->active()->for($team)->create(['name' => 'Ccc']);

        BudgetPeriod::factory()->scheduled()->forTag($later)->for($currency)->create();
        BudgetPeriod::factory()->paused()->forTag($stopped)->for($currency)->create();
        BudgetPeriod::factory()->singleMonth(CarbonImmutable::now()->subMonths(2))->forTag($objective)->for($currency)->create();

        $this->get(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->has('budgets.data', 3)
                    ->where('budgets.data.0.status', BudgetPeriodStatus::Scheduled->value)
                    ->where('budgets.data.1.status', BudgetPeriodStatus::Paused->value)
                    ->where('budgets.data.2.status', BudgetPeriodStatus::Ended->value)));
    });

    it('reports a replaced stretch as ended rather than paused', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        BudgetPeriod::factory()->forTag($tag)->for($currency)->create([
            'amount' => '300',
            'from_month' => CarbonImmutable::now()->subMonths(4),
            'until_month' => CarbonImmutable::now()->subMonth(),
        ]);
        BudgetPeriod::factory()->running()->forTag($tag)->for($currency)->create(['amount' => '500']);

        $this->get(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->where('budgets.data.0.status', BudgetPeriodStatus::Running->value)
                    ->where('budgets.data.1.status', BudgetPeriodStatus::Ended->value)));
    });

    it('lists the budgets of a deactivated tag and labels it', function (): void {
        [$user, $team, $currency] = budgetingTeam();
        $retired = Tag::factory()->inactive()->for($team)->create(['name' => 'Retired']);
        BudgetPeriod::factory()->running()->forTag($retired)->for($currency)->create();

        $this->get(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->has('budgets.data', 1)
                    ->where('budgets.data.0.target.tag.is_active', false)));
    });

    it('lists a category budget with its label and icon', function (): void {
        [$user, $team, $currency] = budgetingTeam();
        BudgetPeriod::factory()->running()->forCategory(Category::Utilities)->for($team)->for($currency)->create(['amount' => '8000']);

        $this->get(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('categories')
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->has('budgets.data', 1)
                    ->where('budgets.data.0.target.kind', BudgetTargetKind::Category->value)
                    ->where('budgets.data.0.target.category.value', Category::Utilities->value)
                    ->where('budgets.data.0.target.category.icon', Category::Utilities->icon())
                    ->where('budgets.data.0.status', BudgetPeriodStatus::Running->value)));
    });

    it('sums the month movements of the budgeted category', function (): void {
        [$user, $team, $currency, $tag, $account] = budgetingTeam();
        BudgetPeriod::factory()->running()->forCategory(Category::Groceries)->for($team)->for($currency)->create(['amount' => '500']);

        Transaction::factory()->expense()->for($account)->for($currency)->create([
            'amount' => '200',
            'category' => Category::Groceries,
            'occurred_at' => CarbonImmutable::now()->startOfMonth()->addDay(),
        ]);
        Transaction::factory()->income()->for($account)->for($currency)->create([
            'amount' => '100',
            'category' => Category::Groceries,
            'occurred_at' => CarbonImmutable::now()->startOfMonth()->addDay(),
        ]);
        Transaction::factory()->expense()->for($account)->for($currency)->create([
            'amount' => '900',
            'category' => Category::Transport,
            'occurred_at' => CarbonImmutable::now()->startOfMonth()->addDay(),
        ]);

        $this->get(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->where('budgets.data.0.progress.spent', fn (mixed $value): bool => (float) $value === 200.0)
                    ->where('budgets.data.0.progress.income', fn (mixed $value): bool => (float) $value === 100.0)
                    ->where('budgets.data.0.progress.remaining', fn (mixed $value): bool => (float) $value === 400.0)));
    });

    it('counts a movement under its category budget and its tag budget at once', function (): void {
        [$user, $team, $currency, $tag, $account] = budgetingTeam();
        BudgetPeriod::factory()->running()->forCategory(Category::Groceries)->for($team)->for($currency)->create(['amount' => '500']);
        BudgetPeriod::factory()->running()->forTag($tag)->for($currency)->create(['amount' => '500']);

        $spent = Transaction::factory()->expense()->for($account)->for($currency)->create([
            'amount' => '200',
            'category' => Category::Groceries,
            'occurred_at' => CarbonImmutable::now()->startOfMonth()->addDay(),
        ]);
        $spent->attachTags([$tag->getKey()]);

        $this->get(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->has('budgets.data', 2)
                    ->where('budgets.data.0.progress.spent', fn (mixed $value): bool => (float) $value === 200.0)
                    ->where('budgets.data.1.progress.spent', fn (mixed $value): bool => (float) $value === 200.0)));
    });

    it('lists a sub-budget under its category budget with what is left unallocated', function (): void {
        [$user, $team, $currency] = budgetingTeam();
        $parent = BudgetPeriod::factory()->running()->forCategory(Category::Utilities)->for($team)->for($currency)->create(['amount' => '5000']);
        $child = BudgetPeriod::factory()->within($parent)->running()->create(['amount' => '2000']);
        $child->tag->update(['name' => 'Electricity']);
        BudgetPeriod::factory()->within($parent)->scheduled()->create(['amount' => '900']);

        $this->get(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->has('budgets.data', 1)
                    ->where('budgets.data.0.id', $parent->getKey())
                    ->has('budgets.data.0.children', 2)
                    ->where('budgets.data.0.children.0.id', $child->getKey())
                    ->where('budgets.data.0.children.0.parent_id', $parent->getKey())
                    ->where('budgets.data.0.children.0.target.tag.name', 'Electricity')
                    ->where('budgets.data.0.children.0.status', BudgetPeriodStatus::Running->value)
                    ->where('budgets.data.0.children.1.status', BudgetPeriodStatus::Scheduled->value)
                    ->where('budgets.data.0.allocation.allocated', fn (mixed $value): bool => (float) $value === 2000.0)
                    ->where('budgets.data.0.allocation.unallocated', fn (mixed $value): bool => (float) $value === 3000.0)
                    ->where('budgets.data.0.children.0.allocation', null)));
    });

    it('reports a negative rest when the sub-budgets claim more than their parent', function (): void {
        [$user, $team, $currency] = budgetingTeam();
        $parent = BudgetPeriod::factory()->running()->forCategory(Category::Utilities)->for($team)->for($currency)->create(['amount' => '1000']);
        BudgetPeriod::factory()->within($parent)->running()->create(['amount' => '1500']);

        $this->get(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->where('budgets.data.0.allocation.unallocated', fn (mixed $value): bool => (float) $value === -500.0)));
    });

    it('counts only the tag movements within the parent category for a sub-budget', function (): void {
        [$user, $team, $currency, $tag, $account] = budgetingTeam();
        $parent = BudgetPeriod::factory()->running()->forCategory(Category::Groceries)->for($team)->for($currency)->create(['amount' => '500']);
        BudgetPeriod::factory()->within($parent)->running()->create([
            'amount' => '300',
            'tag_id' => $tag->getKey(),
        ]);
        $day = CarbonImmutable::now()->startOfMonth()->addDay();

        Transaction::factory()->expense()->for($account)->for($currency)->create([
            'amount' => '200',
            'category' => Category::Groceries,
            'occurred_at' => $day,
        ])->attachTags([$tag->getKey()]);
        Transaction::factory()->expense()->for($account)->for($currency)->create([
            'amount' => '150',
            'category' => Category::Transport,
            'occurred_at' => $day,
        ])->attachTags([$tag->getKey()]);
        Transaction::factory()->expense()->for($account)->for($currency)->create([
            'amount' => '50',
            'category' => Category::Groceries,
            'occurred_at' => $day,
        ]);

        $this->get(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->where('budgets.data.0.progress.spent', fn (mixed $value): bool => (float) $value === 250.0)
                    ->where('budgets.data.0.children.0.progress.spent', fn (mixed $value): bool => (float) $value === 200.0)
                    ->where('budgets.data.0.children.0.progress.remaining', fn (mixed $value): bool => (float) $value === 100.0)));
    });

    it('keeps a category budget of a downgraded team without its progress', function (): void {
        [$user, $team, $currency] = budgetingTeam();
        BudgetPeriod::factory()->running()->forCategory(Category::Utilities)->for($team)->for($currency)->create(['amount' => '8000']);
        cancelPro($team);

        $this->get(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('is_locked', true)
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->has('budgets.data', 1)
                    ->where('budgets.data.0.target.category.value', Category::Utilities->value)));
    });

    it('offers only the currencies the team holds on an active account', function (): void {
        [$user, $team, $currency, $tag, $account] = budgetingTeam();
        Currency::factory()->active()->fiat()->create(['code' => 'USD']);
        $unused = Currency::factory()->active()->fiat()->create(['code' => 'EUR']);
        Account::factory()->inactive()->for($team)->create()->currencies()->attach($unused, ['balance' => '0']);

        $this->get(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('currencies', 1)
                ->where('currencies.0.code', 'ARS'));
    });

    it('locks the page for a free team instead of failing', function (): void {
        [$user, $team] = actingMember();

        $this->get(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('finance/teams/budgets/index')
                ->where('is_locked', true));
    });

    it('keeps listing the budgets a free team already recorded', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        BudgetPeriod::factory()->running()->forTag($tag)->for($currency)->create(['amount' => '500']);
        cancelPro($team);

        $this->get(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('finance/teams/budgets/index')
                ->where('is_locked', true)
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->has('budgets.data', 1)
                    ->where('budgets.data.0.target.tag.name', 'Food')));
    });
});

describe('create', function (): void {
    it('renders the create budget modal', function (): void {
        [$user, $team] = budgetingTeam();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.budgets.create', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('finance/teams/budgets/create'));
    });

    it('offers the category budgets a tag budget may sit under', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        $running = BudgetPeriod::factory()->running()->forCategory(Category::Utilities)->for($team)->for($currency)->create();
        BudgetPeriod::factory()->scheduled()->forCategory(Category::Groceries)->for($team)->for($currency)->create();
        BudgetPeriod::factory()->ended()->forCategory(Category::Transport)->for($team)->for($currency)->create();
        BudgetPeriod::factory()->running()->forTag($tag)->for($currency)->create();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.budgets.create', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('parents', 2)
                ->where('parents.1.id', $running->getKey())
                ->where('parents.1.category.value', Category::Utilities->value)
                ->where('parents.1.currency_id', $currency->getKey()));
    });

    it('redirects a free team to the upgrade modal', function (): void {
        [$user, $team] = actingMember();

        $this->get(route('finance.teams.budgets.create', ['team' => $team->getRouteKey()]))
            ->assertRedirect(route('billing.teams.subscriptions.upgrade', [
                'team' => $team->getRouteKey(),
                'feature' => 'budgets:manage',
            ]));
    });
});

describe('store', function (): void {
    it('opens a budget period on the tag', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();

        $this->from(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.budgets.store', ['team' => $team->getRouteKey()]), budgetPayload($tag, $currency))
            ->assertRedirect();

        assertFlashedToast(trans('Saved'));

        $period = BudgetPeriod::query()->sole();

        expect((float) $period->amount)->toBe(500.0)
            ->and($period->tag->is($tag))->toBeTrue()
            ->and($period->from_month->format('Y-m'))->toBe(CarbonImmutable::now()->format('Y-m'))
            ->and($period->until_month)->toBeNull();
    });

    it('checks a shorter month for overlaps when today is past its last day', function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-01-31 12:00:00'));
        [$user, $team, $currency, $tag] = budgetingTeam();
        BudgetPeriod::factory()->forTag($tag)->for($currency)->create([
            'from_month' => '2026-02-01',
            'until_month' => '2026-02-01',
        ]);

        $this->post(route('finance.teams.budgets.store', ['team' => $team->getRouteKey()]), budgetPayload($tag, $currency, [
            'from_month' => '2026-02',
            'until_month' => '2026-02',
        ]))->assertInvalid(['from_month']);

        expect(BudgetPeriod::query()->count())->toBe(1);
    });

    it('opens a single month objective', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        $month = CarbonImmutable::now()->format('Y-m');

        $this->post(route('finance.teams.budgets.store', ['team' => $team->getRouteKey()]), budgetPayload($tag, $currency, [
            'until_month' => $month,
        ]))->assertRedirect();

        $period = BudgetPeriod::query()->sole();

        expect($period->until_month?->format('Y-m'))->toBe($month);
    });

    it('opens a back dated period', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        $past = CarbonImmutable::now()->subMonths(4);

        $this->post(route('finance.teams.budgets.store', ['team' => $team->getRouteKey()]), budgetPayload($tag, $currency, [
            'from_month' => $past->format('Y-m'),
            'until_month' => $past->format('Y-m'),
        ]))->assertRedirect();

        expect(BudgetPeriod::query()->sole()->from_month->format('Y-m'))->toBe($past->format('Y-m'));
    });

    it('rejects a period overlapping another on the same tag and currency', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        BudgetPeriod::factory()->running()->forTag($tag)->for($currency)->create();

        $this->from(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.budgets.store', ['team' => $team->getRouteKey()]), budgetPayload($tag, $currency))
            ->assertRedirect()
            ->assertInvalid(['from_month']);

        expect(BudgetPeriod::query()->count())->toBe(1);
    });

    it('skips overlap checks when the first month cannot be read', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();

        $this->from(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.budgets.store', ['team' => $team->getRouteKey()]), budgetPayload($tag, $currency, [
                'from_month' => '',
            ]))
            ->assertRedirect()
            ->assertInvalid(['from_month']);
    });

    it('skips the held-currency check when the key is not a string', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();

        $this->from(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.budgets.store', ['team' => $team->getRouteKey()]), budgetPayload($tag, $currency, [
                'currency_id' => [$currency->getKey()],
            ]))
            ->assertRedirect()
            ->assertInvalid(['currency_id']);
    });

    it('allows the same months on another currency of the same tag', function (): void {
        [$user, $team, $currency, $tag, $account] = budgetingTeam();
        $dollar = Currency::factory()->active()->fiat()->create(['code' => 'USD', 'decimal_places' => 2]);
        $account->currencies()->attach($dollar, ['balance' => '0']);
        BudgetPeriod::factory()->running()->forTag($tag)->for($currency)->create();

        $this->post(route('finance.teams.budgets.store', ['team' => $team->getRouteKey()]), budgetPayload($tag, $dollar))
            ->assertRedirect();

        expect(BudgetPeriod::query()->count())->toBe(2);
    });

    it('opens a budget period on a category', function (): void {
        [$user, $team, $currency] = budgetingTeam();

        $this->from(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.budgets.store', ['team' => $team->getRouteKey()]), categoryBudgetPayload(Category::Utilities, $currency))
            ->assertRedirect();

        assertFlashedToast(trans('Saved'));

        $period = BudgetPeriod::query()->sole();

        expect($period->category)->toBe(Category::Utilities)
            ->and($period->tag_id)->toBeNull()
            ->and($period->team_id)->toBe($team->getKey())
            ->and((float) $period->amount)->toBe(8000.0);
    });

    it('rejects a payload carrying both a category and a tag', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();

        $this->from(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.budgets.store', ['team' => $team->getRouteKey()]), categoryBudgetPayload(Category::Utilities, $currency, [
                'tag_id' => $tag->getKey(),
            ]))
            ->assertRedirect()
            ->assertInvalid(['tag_id']);

        expect(BudgetPeriod::query()->count())->toBe(0);
    });

    it('rejects a payload carrying neither a category nor a tag', function (): void {
        [$user, $team, $currency] = budgetingTeam();

        $this->from(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.budgets.store', ['team' => $team->getRouteKey()]), categoryBudgetPayload(Category::Utilities, $currency, [
                'category' => null,
            ]))
            ->assertRedirect()
            ->assertInvalid(['category']);

        expect(BudgetPeriod::query()->count())->toBe(0);
    });

    it('rejects a category budget overlapping another of the same category and currency', function (): void {
        [$user, $team, $currency] = budgetingTeam();
        BudgetPeriod::factory()->running()->forCategory(Category::Utilities)->for($team)->for($currency)->create();

        $this->from(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.budgets.store', ['team' => $team->getRouteKey()]), categoryBudgetPayload(Category::Utilities, $currency))
            ->assertRedirect()
            ->assertInvalid(['from_month']);

        expect(BudgetPeriod::query()->count())->toBe(1);
    });

    it('allows the same category months in another currency', function (): void {
        [$user, $team, $currency, $tag, $account] = budgetingTeam();
        $dollar = Currency::factory()->active()->fiat()->create(['code' => 'USD', 'decimal_places' => 2]);
        $account->currencies()->attach($dollar, ['balance' => '0']);
        BudgetPeriod::factory()->running()->forCategory(Category::Utilities)->for($team)->for($currency)->create();

        $this->post(route('finance.teams.budgets.store', ['team' => $team->getRouteKey()]), categoryBudgetPayload(Category::Utilities, $dollar))
            ->assertRedirect();

        expect(BudgetPeriod::query()->count())->toBe(2);
    });

    it('allows a category budget beside a tag budget on the same months', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        BudgetPeriod::factory()->running()->forTag($tag)->for($currency)->create();

        $this->post(route('finance.teams.budgets.store', ['team' => $team->getRouteKey()]), categoryBudgetPayload(Category::Groceries, $currency))
            ->assertRedirect();

        expect(BudgetPeriod::query()->count())->toBe(2);
    });

    it('nests a tag budget under a category budget', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        $parent = BudgetPeriod::factory()->running()->forCategory(Category::Groceries)->for($team)->for($currency)->create();

        $this->from(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.budgets.store', ['team' => $team->getRouteKey()]), budgetPayload($tag, $currency, [
                'parent_id' => $parent->getKey(),
            ]))
            ->assertRedirect()
            ->assertValid();

        $period = BudgetPeriod::query()->whereKeyNot($parent)->sole();

        expect($period->parent_id)->toBe($parent->getKey())
            ->and($period->tag->is($tag))->toBeTrue();
    });

    it('opens a plain tag budget when the parent is left empty', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();

        $this->post(route('finance.teams.budgets.store', ['team' => $team->getRouteKey()]), budgetPayload($tag, $currency, [
            'parent_id' => '',
        ]))->assertRedirect()->assertValid();

        expect(BudgetPeriod::query()->sole()->parent_id)->toBeNull();
    });

    it('rejects a parent on a category budget', function (): void {
        [$user, $team, $currency] = budgetingTeam();
        $parent = BudgetPeriod::factory()->running()->forCategory(Category::Groceries)->for($team)->for($currency)->create();

        $this->from(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.budgets.store', ['team' => $team->getRouteKey()]), categoryBudgetPayload(Category::Utilities, $currency, [
                'parent_id' => $parent->getKey(),
            ]))
            ->assertRedirect()
            ->assertInvalid(['parent_id' => trans('Only a tag budget can be part of a category budget.')]);
    });

    it('rejects a parent that is a tag budget', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        $other = Tag::factory()->active()->for($team)->create();
        $parent = BudgetPeriod::factory()->running()->forTag($other)->for($currency)->create();

        $this->from(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.budgets.store', ['team' => $team->getRouteKey()]), budgetPayload($tag, $currency, [
                'parent_id' => $parent->getKey(),
            ]))
            ->assertRedirect()
            ->assertInvalid(['parent_id' => trans('Choose one of the category budgets of your team.')]);
    });

    it('rejects a parent of another team', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        $parent = BudgetPeriod::factory()->running()->forCategory(Category::Groceries)->for(Team::factory())->for($currency)->create();

        $this->from(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.budgets.store', ['team' => $team->getRouteKey()]), budgetPayload($tag, $currency, [
                'parent_id' => $parent->getKey(),
            ]))
            ->assertRedirect()
            ->assertInvalid(['parent_id']);
    });

    it('rejects a parent in another currency', function (): void {
        [$user, $team, $currency, $tag, $account] = budgetingTeam();
        $dollar = Currency::factory()->active()->fiat()->create(['code' => 'USD', 'decimal_places' => 2]);
        $account->currencies()->attach($dollar, ['balance' => '0']);
        $parent = BudgetPeriod::factory()->running()->forCategory(Category::Groceries)->for($team)->for($dollar)->create();

        $this->from(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.budgets.store', ['team' => $team->getRouteKey()]), budgetPayload($tag, $currency, [
                'parent_id' => $parent->getKey(),
            ]))
            ->assertRedirect()
            ->assertInvalid(['parent_id' => trans('The category budget must be in the same currency.')]);
    });

    it('rejects a category that is not one of the known ones', function (): void {
        [$user, $team, $currency] = budgetingTeam();

        $this->from(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.budgets.store', ['team' => $team->getRouteKey()]), categoryBudgetPayload(Category::Utilities, $currency, [
                'category' => 'yachts',
            ]))
            ->assertRedirect()
            ->assertInvalid(['category']);
    });

    it('rejects a currency the team does not hold on an active account', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        $foreign = Currency::factory()->active()->fiat()->create(['code' => 'EUR']);

        $this->from(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.budgets.store', ['team' => $team->getRouteKey()]), budgetPayload($tag, $foreign))
            ->assertRedirect()
            ->assertInvalid(['currency_id']);
    });

    it('rejects a tag of another team', function (): void {
        [$user, $team, $currency] = budgetingTeam();
        $foreign = Tag::factory()->for(Team::factory())->create();

        $this->from(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.budgets.store', ['team' => $team->getRouteKey()]), budgetPayload($foreign, $currency))
            ->assertRedirect()
            ->assertInvalid(['tag_id']);
    });

    it('rejects a last month before the first month', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();

        $this->from(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.budgets.store', ['team' => $team->getRouteKey()]), budgetPayload($tag, $currency, [
                'until_month' => CarbonImmutable::now()->subMonth()->format('Y-m'),
            ]))
            ->assertRedirect()
            ->assertInvalid(['from_month']);
    });

    it('rejects an amount that is not positive', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();

        $this->from(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.budgets.store', ['team' => $team->getRouteKey()]), budgetPayload($tag, $currency, [
                'amount' => '0',
            ]))
            ->assertRedirect()
            ->assertInvalid(['amount']);
    });

    it('stores the warning threshold', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();

        $this->post(route('finance.teams.budgets.store', ['team' => $team->getRouteKey()]), budgetPayload($tag, $currency, [
            'warning_threshold' => '80',
        ]))->assertRedirect()->assertValid();

        expect(BudgetPeriod::query()->sole()->warning_threshold)->toBe(80);
    });

    it('stores no warning threshold when it is left empty', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();

        $this->post(route('finance.teams.budgets.store', ['team' => $team->getRouteKey()]), budgetPayload($tag, $currency, [
            'warning_threshold' => '',
        ]))->assertRedirect()->assertValid();

        expect(BudgetPeriod::query()->sole()->warning_threshold)->toBeNull();
    });

    it('rejects a warning threshold outside 1 to 99', function (mixed $threshold): void {
        [$user, $team, $currency, $tag] = budgetingTeam();

        $this->from(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.budgets.store', ['team' => $team->getRouteKey()]), budgetPayload($tag, $currency, [
                'warning_threshold' => $threshold,
            ]))
            ->assertRedirect()
            ->assertInvalid(['warning_threshold']);

        expect(BudgetPeriod::query()->count())->toBe(0);
    })->with([
        'zero' => '0',
        'a hundred' => '100',
        'a fraction' => '50.5',
        'a word' => 'high',
    ]);

    it('redirects a free team to the upgrade modal', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create();
        $tag = Tag::factory()->for($team)->create();

        $this->post(route('finance.teams.budgets.store', ['team' => $team->getRouteKey()]), budgetPayload($tag, $currency))
            ->assertRedirect(route('billing.teams.subscriptions.upgrade', [
                'team' => $team->getRouteKey(),
                'feature' => 'budgets:manage',
            ]));

        expect(BudgetPeriod::query()->count())->toBe(0);
    });

    it('records the budget creation in the activity log', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        clearActivities();

        $this->post(route('finance.teams.budgets.store', ['team' => $team->getRouteKey()]), budgetPayload($tag, $currency))
            ->assertRedirect();

        $activity = activitiesOf($team)->sole();

        expect($activity->event)->toBe('created')
            ->and($activity->causer?->is($user))->toBeTrue()
            ->and($activity->subject?->is(BudgetPeriod::query()->sole()))->toBeTrue();
    });
});

describe('edit', function (): void {
    it('renders the edit budget modal', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        $period = BudgetPeriod::factory()->running()->forTag($tag)->for($currency)->create();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.budgets.edit', [
                'team' => $team->getRouteKey(),
                'budget' => $period->getKey(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('finance/teams/budgets/edit')
                ->where('budget.id', $period->getKey()));
    });

    it('prefills the warning threshold', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        $period = BudgetPeriod::factory()->running()->withWarningThreshold(75)->forTag($tag)->for($currency)->create();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.budgets.edit', [
                'team' => $team->getRouteKey(),
                'budget' => $period->getKey(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('budget.warning_threshold', 75));
    });

    it('prefills the parent of a sub-budget and offers the category budgets', function (): void {
        [$user, $team, $currency] = budgetingTeam();
        $parent = BudgetPeriod::factory()->ended()->forCategory(Category::Utilities)->for($team)->for($currency)->create();
        $period = BudgetPeriod::factory()->within($parent)->running()->create();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.budgets.edit', [
                'team' => $team->getRouteKey(),
                'budget' => $period->getKey(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('budget.parent_id', $parent->getKey())
                // The parent it sits under is offered even once it is over.
                ->has('parents', 1)
                ->where('parents.0.id', $parent->getKey()));
    });

    it('returns 404 when the budget belongs to another team', function (): void {
        [$user, $team] = budgetingTeam();
        $foreign = BudgetPeriod::factory()->running()->forTag(Tag::factory()->for(Team::factory())->create())->create();

        $this->get(route('finance.teams.budgets.edit', [
            'team' => $team->getRouteKey(),
            'budget' => $foreign->getKey(),
        ]))->assertNotFound();
    });
});

describe('update', function (): void {
    it('changes the amount and the months', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        $period = BudgetPeriod::factory()->running()->forTag($tag)->for($currency)->create(['amount' => '500']);
        $next = CarbonImmutable::now()->addMonth();

        $this->from(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.budgets.update', [
                'team' => $team->getRouteKey(),
                'budget' => $period->getKey(),
            ]), [
                'amount' => '650.00',
                'currency_id' => $currency->getKey(),
                'from_month' => CarbonImmutable::now()->format('Y-m'),
                'until_month' => $next->format('Y-m'),
            ])
            ->assertRedirect();

        assertFlashedToast(trans('Saved'));

        $period->refresh();

        expect((float) $period->amount)->toBe(650.0)
            ->and($period->until_month?->format('Y-m'))->toBe($next->format('Y-m'));
    });

    it('moves a tag budget under a category budget and back out', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        $parent = BudgetPeriod::factory()->running()->forCategory(Category::Groceries)->for($team)->for($currency)->create();
        $period = BudgetPeriod::factory()->running()->forTag($tag)->for($currency)->create(['amount' => '500']);
        $payload = [
            'amount' => '500.00',
            'currency_id' => $currency->getKey(),
            'from_month' => CarbonImmutable::now()->format('Y-m'),
            'until_month' => null,
        ];

        $this->patch(route('finance.teams.budgets.update', [
            'team' => $team->getRouteKey(),
            'budget' => $period->getKey(),
        ]), [...$payload, 'parent_id' => $parent->getKey()])->assertRedirect()->assertValid();

        expect($period->refresh()->parent_id)->toBe($parent->getKey());

        $this->patch(route('finance.teams.budgets.update', [
            'team' => $team->getRouteKey(),
            'budget' => $period->getKey(),
        ]), [...$payload, 'parent_id' => ''])->assertRedirect()->assertValid();

        expect($period->refresh()->parent_id)->toBeNull();
    });

    it('rejects a parent on a category budget edit', function (): void {
        [$user, $team, $currency] = budgetingTeam();
        $parent = BudgetPeriod::factory()->running()->forCategory(Category::Groceries)->for($team)->for($currency)->create();
        $period = BudgetPeriod::factory()->running()->forCategory(Category::Utilities)->for($team)->for($currency)->create(['amount' => '500']);

        $this->from(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.budgets.update', [
                'team' => $team->getRouteKey(),
                'budget' => $period->getKey(),
            ]), [
                'amount' => '500.00',
                'currency_id' => $currency->getKey(),
                'from_month' => CarbonImmutable::now()->format('Y-m'),
                'parent_id' => $parent->getKey(),
                'until_month' => null,
            ])
            ->assertRedirect()
            ->assertInvalid(['parent_id']);
    });

    it('changes the warning threshold and logs the change', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        $period = BudgetPeriod::factory()->running()->withWarningThreshold(80)->forTag($tag)->for($currency)->create(['amount' => '500']);
        clearActivities();

        $this->patch(route('finance.teams.budgets.update', [
            'team' => $team->getRouteKey(),
            'budget' => $period->getKey(),
        ]), [
            'amount' => '500',
            'currency_id' => $currency->getKey(),
            'from_month' => CarbonImmutable::now()->format('Y-m'),
            'warning_threshold' => '90',
        ])->assertRedirect()->assertValid();

        $activity = activitiesOf($team, 'updated')->sole();

        expect($period->refresh()->warning_threshold)->toBe(90)
            ->and($activity->attribute_changes['old'] ?? null)->toBe(['warning_threshold' => 80])
            ->and($activity->attribute_changes['attributes'] ?? null)->toBe(['warning_threshold' => 90]);
    });

    it('drops the warning threshold when it is emptied', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        $period = BudgetPeriod::factory()->running()->withWarningThreshold(80)->forTag($tag)->for($currency)->create();

        $this->patch(route('finance.teams.budgets.update', [
            'team' => $team->getRouteKey(),
            'budget' => $period->getKey(),
        ]), [
            'amount' => '500',
            'currency_id' => $currency->getKey(),
            'from_month' => CarbonImmutable::now()->format('Y-m'),
            'warning_threshold' => '',
        ])->assertRedirect()->assertValid();

        expect($period->refresh()->warning_threshold)->toBeNull();
    });

    it('rejects a warning threshold of a hundred', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        $period = BudgetPeriod::factory()->running()->withWarningThreshold(80)->forTag($tag)->for($currency)->create();

        $this->from(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.budgets.update', [
                'team' => $team->getRouteKey(),
                'budget' => $period->getKey(),
            ]), [
                'amount' => '500',
                'currency_id' => $currency->getKey(),
                'from_month' => CarbonImmutable::now()->format('Y-m'),
                'warning_threshold' => '100',
            ])
            ->assertRedirect()
            ->assertInvalid(['warning_threshold']);

        expect($period->refresh()->warning_threshold)->toBe(80);
    });

    it('rejects months another stretch already covers', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        BudgetPeriod::factory()->forTag($tag)->for($currency)->create([
            'amount' => '300',
            'from_month' => CarbonImmutable::now()->subMonths(4),
            'until_month' => CarbonImmutable::now()->subMonth(),
        ]);
        $period = BudgetPeriod::factory()->running()->forTag($tag)->for($currency)->create();

        $this->from(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.budgets.update', [
                'team' => $team->getRouteKey(),
                'budget' => $period->getKey(),
            ]), [
                'amount' => '500',
                'currency_id' => $currency->getKey(),
                'from_month' => CarbonImmutable::now()->subMonths(2)->format('Y-m'),
                'until_month' => null,
            ])
            ->assertRedirect()
            ->assertInvalid(['from_month']);
    });

    it('lets a stretch keep its own months', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        $period = BudgetPeriod::factory()->running()->forTag($tag)->for($currency)->create(['amount' => '500']);

        $this->patch(route('finance.teams.budgets.update', [
            'team' => $team->getRouteKey(),
            'budget' => $period->getKey(),
        ]), [
            'amount' => '700',
            'currency_id' => $currency->getKey(),
            'from_month' => $period->from_month->format('Y-m'),
            'until_month' => null,
        ])->assertRedirect();

        expect((float) $period->fresh()?->amount)->toBe(700.0);
    });

    it('keeps the target of a category budget on an edit', function (): void {
        [$user, $team, $currency] = budgetingTeam();
        $period = BudgetPeriod::factory()->running()->forCategory(Category::Utilities)->for($team)->for($currency)->create(['amount' => '8000']);

        $this->patch(route('finance.teams.budgets.update', [
            'team' => $team->getRouteKey(),
            'budget' => $period->getKey(),
        ]), [
            'amount' => '9000',
            'category' => Category::Groceries->value,
            'currency_id' => $currency->getKey(),
            'from_month' => $period->from_month->format('Y-m'),
            'tag_id' => Tag::factory()->for($team)->create()->getKey(),
            'until_month' => null,
        ])->assertRedirect();

        $period->refresh();

        expect((float) $period->amount)->toBe(9000.0)
            ->and($period->category)->toBe(Category::Utilities)
            ->and($period->tag_id)->toBeNull();
    });

    it('redirects a free team to the upgrade modal', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        $period = BudgetPeriod::factory()->running()->forTag($tag)->for($currency)->create(['amount' => '500']);
        $team->subscriptions()->delete();
        $team->unsetRelation('subscriptions');

        $this->patch(route('finance.teams.budgets.update', [
            'team' => $team->getRouteKey(),
            'budget' => $period->getKey(),
        ]), [
            'amount' => '900',
            'currency_id' => $currency->getKey(),
            'from_month' => $period->from_month->format('Y-m'),
        ])->assertRedirect(route('billing.teams.subscriptions.upgrade', [
            'team' => $team->getRouteKey(),
            'feature' => 'budgets:manage',
        ]));

        expect((float) $period->fresh()?->amount)->toBe(500.0);
    });

    it('re-arms the warning once a raised amount puts the month back under it', function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-09-15 12:00:00'));
        [$user, $team, $currency, $tag, $account] = budgetingTeam();
        $period = BudgetPeriod::factory()->forCategory(Category::Groceries)->running()->for($team)->for($currency)->withWarningThreshold(80)->create(['amount' => '100']);
        Notification::fake();
        $spend = fn (string $amount) => $this->post(route('finance.teams.transactions.store', ['team' => $team->getRouteKey()]), [
            $account->getForeignKey() => $account->getKey(),
            'amount' => $amount,
            'category' => Category::Groceries->value,
            $currency->getForeignKey() => $currency->getKey(),
            'occurred_at' => CarbonImmutable::now()->toDateTimeString(),
            'quote' => '1',
            'type' => TransactionType::Expense->value,
        ])->assertRedirect();

        $spend('85');
        $this->patch(route('finance.teams.budgets.update', ['team' => $team->getRouteKey(), 'budget' => $period->getKey()]), [
            'amount' => '200.00',
            'currency_id' => $currency->getKey(),
            'from_month' => CarbonImmutable::now()->format('Y-m'),
            'warning_threshold' => 80,
        ])->assertRedirect();
        $spend('90');

        Notification::assertSentToTimes($user, BudgetAlertNotification::class, 2);
    });
});

describe('pause', function (): void {
    it('stops a category budget at the end of the current month', function (): void {
        [$user, $team, $currency] = budgetingTeam();
        $period = BudgetPeriod::factory()->forCategory(Category::Utilities)->for($team)->for($currency)->create([
            'amount' => '8000',
            'from_month' => CarbonImmutable::now()->subMonths(3),
            'until_month' => null,
        ]);

        $this->patch(route('finance.teams.budgets.pause', [
            'team' => $team->getRouteKey(),
            'budget' => $period->getKey(),
        ]))->assertRedirect();

        expect($period->fresh()?->until_month?->format('Y-m'))->toBe(CarbonImmutable::now()->format('Y-m'));
    });

    it('stops the running stretch at the end of the current month', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        $period = BudgetPeriod::factory()->forTag($tag)->for($currency)->create([
            'amount' => '500',
            'from_month' => CarbonImmutable::now()->subMonths(3),
            'until_month' => null,
        ]);

        $this->from(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.budgets.pause', [
                'team' => $team->getRouteKey(),
                'budget' => $period->getKey(),
            ]))
            ->assertRedirect();

        assertFlashedToast(trans('Saved'));

        expect($period->fresh()?->until_month?->format('Y-m'))->toBe(CarbonImmutable::now()->format('Y-m'));
    });

    it('refuses a budget that already has a last month', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        $until = CarbonImmutable::now()->addMonths(2)->startOfMonth();
        $period = BudgetPeriod::factory()->forTag($tag)->for($currency)->create([
            'from_month' => CarbonImmutable::now()->subMonth(),
            'until_month' => $until,
        ]);

        $this->patch(route('finance.teams.budgets.pause', [
            'team' => $team->getRouteKey(),
            'budget' => $period->getKey(),
        ]))->assertConflict();

        expect($period->fresh()?->until_month?->format('Y-m'))->toBe($until->format('Y-m'));
    });
});

describe('resume', function (): void {
    it('reopens a category budget on the same category', function (): void {
        [$user, $team, $currency] = budgetingTeam();
        $period = BudgetPeriod::factory()->paused()->forCategory(Category::Utilities)->for($team)->for($currency)->create(['amount' => '8000']);

        $this->patch(route('finance.teams.budgets.resume', [
            'team' => $team->getRouteKey(),
            'budget' => $period->getKey(),
        ]))->assertRedirect();

        $resumed = BudgetPeriod::query()->whereKeyNot($period)->sole();

        expect($resumed->category)->toBe(Category::Utilities)
            ->and($resumed->tag_id)->toBeNull()
            ->and($resumed->from_month->format('Y-m'))->toBe(CarbonImmutable::now()->format('Y-m'));
    });

    it('keeps a resumed sub-budget under its parent', function (): void {
        [$user, $team, $currency] = budgetingTeam();
        $parent = BudgetPeriod::factory()->running()->forCategory(Category::Utilities)->for($team)->for($currency)->create();
        $period = BudgetPeriod::factory()->within($parent)->paused()->create();

        $this->patch(route('finance.teams.budgets.resume', [
            'team' => $team->getRouteKey(),
            'budget' => $period->getKey(),
        ]))->assertRedirect();

        $resumed = BudgetPeriod::query()->whereKeyNot($period)->whereKeyNot($parent)->sole();

        expect($resumed->parent_id)->toBe($parent->getKey());
    });

    it('opens a fresh stretch from the current month on the same amount', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        $period = BudgetPeriod::factory()->paused()->forTag($tag)->for($currency)->create(['amount' => '400']);

        $this->from(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.budgets.resume', [
                'team' => $team->getRouteKey(),
                'budget' => $period->getKey(),
            ]))
            ->assertRedirect();

        assertFlashedToast(trans('Saved'));

        $resumed = BudgetPeriod::query()->whereKeyNot($period)->sole();

        expect((float) $resumed->amount)->toBe(400.0)
            ->and($resumed->from_month->format('Y-m'))->toBe(CarbonImmutable::now()->format('Y-m'))
            ->and($resumed->until_month)->toBeNull();
    });

    it('keeps the warning threshold of the stopped stretch', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        $period = BudgetPeriod::factory()->paused()->withWarningThreshold(70)->forTag($tag)->for($currency)->create();

        $this->patch(route('finance.teams.budgets.resume', [
            'team' => $team->getRouteKey(),
            'budget' => $period->getKey(),
        ]))->assertRedirect();

        expect(BudgetPeriod::query()->whereKeyNot($period)->sole()->warning_threshold)->toBe(70);
    });

    it('refuses a budget that is still running', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        $period = BudgetPeriod::factory()->running()->forTag($tag)->for($currency)->create();

        $this->patch(route('finance.teams.budgets.resume', [
            'team' => $team->getRouteKey(),
            'budget' => $period->getKey(),
        ]))->assertConflict();

        expect(BudgetPeriod::query()->count())->toBe(1);
    });
});

describe('destroy', function (): void {
    it('removes the budget period', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        $period = BudgetPeriod::factory()->running()->forTag($tag)->for($currency)->create();

        $this->from(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->delete(route('finance.teams.budgets.destroy', [
                'team' => $team->getRouteKey(),
                'budget' => $period->getKey(),
            ]))
            ->assertRedirect();

        assertFlashedToast(trans('Deleted'));

        expect(BudgetPeriod::query()->count())->toBe(0);
    });

    it('leaves the sub-budgets of a removed category budget on their own', function (): void {
        [$user, $team, $currency] = budgetingTeam();
        $parent = BudgetPeriod::factory()->running()->forCategory(Category::Utilities)->for($team)->for($currency)->create();
        $child = BudgetPeriod::factory()->within($parent)->running()->create();

        $this->delete(route('finance.teams.budgets.destroy', [
            'team' => $team->getRouteKey(),
            'budget' => $parent->getKey(),
        ]))->assertRedirect();

        expect($child->refresh()->parent_id)->toBeNull()
            ->and($child->isNested())->toBeFalse();
    });

    it('returns 404 for a budget of another team', function (): void {
        [$user, $team] = budgetingTeam();
        $foreign = BudgetPeriod::factory()->running()->forTag(Tag::factory()->for(Team::factory())->create())->create();

        $this->delete(route('finance.teams.budgets.destroy', [
            'team' => $team->getRouteKey(),
            'budget' => $foreign->getKey(),
        ]))->assertNotFound();

        expect(BudgetPeriod::query()->count())->toBe(1);
    });

    it('records the removal in the activity log', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        $period = BudgetPeriod::factory()->running()->forTag($tag)->for($currency)->create();
        clearActivities();

        $this->delete(route('finance.teams.budgets.destroy', [
            'team' => $team->getRouteKey(),
            'budget' => $period->getKey(),
        ]))->assertRedirect();

        expect(activitiesOf($team)->sole()->event)->toBe('deleted');
    });
});
