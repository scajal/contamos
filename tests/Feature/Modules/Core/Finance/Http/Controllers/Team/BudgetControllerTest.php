<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetPeriodStatus;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
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
        'until_month' => null,
        ...$overrides,
    ];
}

describe('index', function (): void {
    it('renders a deferred listing of the budgets', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        BudgetPeriod::factory()->running()->for($tag)->for($currency)->create(['amount' => '500']);

        $this->get(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('finance/teams/budgets/index')
                ->where('is_locked', false)
                ->has('currencies', 1)
                ->has('tags', 1)
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->has('budgets.data', 1)
                    ->where('budgets.data.0.tag.name', 'Food')
                    ->where('budgets.data.0.currency.code', 'ARS')
                    ->where('budgets.data.0.status', BudgetPeriodStatus::Running->value)));
    });

    it('reports the current month progress of the running budget', function (): void {
        [$user, $team, $currency, $tag, $account] = budgetingTeam();
        BudgetPeriod::factory()->running()->for($tag)->for($currency)->create(['amount' => '500']);

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

    it('reports a scheduled, a paused and an ended budget', function (): void {
        [$user, $team, $currency] = budgetingTeam();
        $later = Tag::factory()->active()->for($team)->create(['name' => 'Aaa']);
        $stopped = Tag::factory()->active()->for($team)->create(['name' => 'Bbb']);
        $objective = Tag::factory()->active()->for($team)->create(['name' => 'Ccc']);

        BudgetPeriod::factory()->scheduled()->for($later)->for($currency)->create();
        BudgetPeriod::factory()->paused()->for($stopped)->for($currency)->create();
        BudgetPeriod::factory()->singleMonth(CarbonImmutable::now()->subMonths(2))->for($objective)->for($currency)->create();

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
        BudgetPeriod::factory()->for($tag)->for($currency)->create([
            'amount' => '300',
            'from_month' => CarbonImmutable::now()->subMonths(4),
            'until_month' => CarbonImmutable::now()->subMonth(),
        ]);
        BudgetPeriod::factory()->running()->for($tag)->for($currency)->create(['amount' => '500']);

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
        BudgetPeriod::factory()->running()->for($retired)->for($currency)->create();

        $this->get(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->has('budgets.data', 1)
                    ->where('budgets.data.0.tag.is_active', false)));
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
        BudgetPeriod::factory()->running()->for($tag)->for($currency)->create(['amount' => '500']);
        cancelPro($team);

        $this->get(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('finance/teams/budgets/index')
                ->where('is_locked', true)
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->has('budgets.data', 1)
                    ->where('budgets.data.0.tag.name', 'Food')));
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
        BudgetPeriod::factory()->running()->for($tag)->for($currency)->create();

        $this->from(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.budgets.store', ['team' => $team->getRouteKey()]), budgetPayload($tag, $currency))
            ->assertRedirect()
            ->assertInvalid(['from_month']);

        expect(BudgetPeriod::query()->count())->toBe(1);
    });

    it('allows the same months on another currency of the same tag', function (): void {
        [$user, $team, $currency, $tag, $account] = budgetingTeam();
        $dollar = Currency::factory()->active()->fiat()->create(['code' => 'USD', 'decimal_places' => 2]);
        $account->currencies()->attach($dollar, ['balance' => '0']);
        BudgetPeriod::factory()->running()->for($tag)->for($currency)->create();

        $this->post(route('finance.teams.budgets.store', ['team' => $team->getRouteKey()]), budgetPayload($tag, $dollar))
            ->assertRedirect();

        expect(BudgetPeriod::query()->count())->toBe(2);
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
        $period = BudgetPeriod::factory()->running()->for($tag)->for($currency)->create();

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

    it('returns 404 when the budget belongs to another team', function (): void {
        [$user, $team] = budgetingTeam();
        $foreign = BudgetPeriod::factory()->running()->for(Tag::factory()->for(Team::factory()))->create();

        $this->get(route('finance.teams.budgets.edit', [
            'team' => $team->getRouteKey(),
            'budget' => $foreign->getKey(),
        ]))->assertNotFound();
    });
});

describe('update', function (): void {
    it('changes the amount and the months', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        $period = BudgetPeriod::factory()->running()->for($tag)->for($currency)->create(['amount' => '500']);
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

    it('rejects months another stretch already covers', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        BudgetPeriod::factory()->for($tag)->for($currency)->create([
            'amount' => '300',
            'from_month' => CarbonImmutable::now()->subMonths(4),
            'until_month' => CarbonImmutable::now()->subMonth(),
        ]);
        $period = BudgetPeriod::factory()->running()->for($tag)->for($currency)->create();

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
        $period = BudgetPeriod::factory()->running()->for($tag)->for($currency)->create(['amount' => '500']);

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

    it('redirects a free team to the upgrade modal', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        $period = BudgetPeriod::factory()->running()->for($tag)->for($currency)->create(['amount' => '500']);
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
});

describe('pause', function (): void {
    it('stops the running stretch at the end of the current month', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        $period = BudgetPeriod::factory()->for($tag)->for($currency)->create([
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
});

describe('resume', function (): void {
    it('opens a fresh stretch from the current month on the same amount', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        $period = BudgetPeriod::factory()->paused()->for($tag)->for($currency)->create(['amount' => '400']);

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
});

describe('destroy', function (): void {
    it('removes the budget period', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        $period = BudgetPeriod::factory()->running()->for($tag)->for($currency)->create();

        $this->from(route('finance.teams.budgets.index', ['team' => $team->getRouteKey()]))
            ->delete(route('finance.teams.budgets.destroy', [
                'team' => $team->getRouteKey(),
                'budget' => $period->getKey(),
            ]))
            ->assertRedirect();

        assertFlashedToast(trans('Deleted'));

        expect(BudgetPeriod::query()->count())->toBe(0);
    });

    it('returns 404 for a budget of another team', function (): void {
        [$user, $team] = budgetingTeam();
        $foreign = BudgetPeriod::factory()->running()->for(Tag::factory()->for(Team::factory()))->create();

        $this->delete(route('finance.teams.budgets.destroy', [
            'team' => $team->getRouteKey(),
            'budget' => $foreign->getKey(),
        ]))->assertNotFound();

        expect(BudgetPeriod::query()->count())->toBe(1);
    });

    it('records the removal in the activity log', function (): void {
        [$user, $team, $currency, $tag] = budgetingTeam();
        $period = BudgetPeriod::factory()->running()->for($tag)->for($currency)->create();
        clearActivities();

        $this->delete(route('finance.teams.budgets.destroy', [
            'team' => $team->getRouteKey(),
            'budget' => $period->getKey(),
        ]))->assertRedirect();

        expect(activitiesOf($team)->sole()->event)->toBe('deleted');
    });
});
