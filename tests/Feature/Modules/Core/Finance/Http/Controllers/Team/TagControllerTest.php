<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Feature tests for \App\Modules\Core\Finance\Http\Controllers\Team\TagController.
 */

/**
 * Valid payload for storing a \App\Modules\Core\Finance\Models\Team\Tag.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function tagPayload(array $overrides = []): array
{
    return [
        'name' => 'Comida',
        ...$overrides,
    ];
}

describe('index', function (): void {
    it('renders a deferred list of tags', function (): void {
        [$user, $team] = actingMember();
        Tag::factory()->active()->for($team)->create(['name' => 'Comida']);

        $this->get(route('finance.teams.tags.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('finance/teams/tags/index')
                ->has('user')
                ->has('filters')
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('tags.data', 1)
                    ->where('tags.data.0.name', 'Comida')));
    });

    it('exposes pagination urls so the first page can disable previous', function (): void {
        [$user, $team] = actingMember();
        Tag::factory()->active()->for($team)->count(11)->create();

        $this->get(route('finance.teams.tags.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->where('tags.prev_page_url', null)
                    ->where('tags.next_page_url', fn (mixed $url): bool => is_string($url) && $url !== '')
                    ->where('tags.current_page', 1)
                    ->where('tags.last_page', 2)));
    });

    it('filters tags by activity and search', function (): void {
        [$user, $team] = actingMember();
        $matching = Tag::factory()->active()->for($team)->create(['name' => 'Comida']);
        Tag::factory()->inactive()->for($team)->create(['name' => 'Salidas']);

        $this->get(route('finance.teams.tags.index', [
            'team' => $team->getRouteKey(),
            'filter' => [
                'is_active' => 'true',
                'search' => 'Comi',
            ],
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('filters.is_active', 'true')
                ->where('filters.search', 'Comi')
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('tags.data', 1)
                    ->where('tags.data.0.id', $matching->getKey())));
    });

    it('rejects an invalid activity filter', function (): void {
        [$user, $team] = actingMember();

        $this->get(route('finance.teams.tags.index', [
            'team' => $team->getRouteKey(),
            'filter' => ['is_active' => 'not-a-boolean'],
        ]))
            ->assertRedirect()
            ->assertInvalid(['filter.is_active']);
    });
});

describe('create', function (): void {
    it('renders the create tag modal', function (): void {
        [$user, $team] = actingMember();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.tags.create', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('finance/teams/tags/create'));
    });
});

describe('store', function (): void {
    it('creates a tag for the team', function (): void {
        [$user, $team] = actingMember();

        $this->from(route('finance.teams.tags.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.tags.store', ['team' => $team->getRouteKey()]), tagPayload())
            ->assertRedirect();

        assertFlashedToast(trans('Saved'));

        $tag = Tag::query()->sole();
        expect($tag->name)->toBe('Comida')
            ->and($tag->is_active)->toBeTrue()
            ->and($tag->team->is($team))->toBeTrue();
    });

    it('rejects a duplicate name ignoring case', function (): void {
        [$user, $team] = actingMember();
        Tag::factory()->for($team)->create(['name' => 'Comida']);

        $this->from(route('finance.teams.tags.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.tags.store', ['team' => $team->getRouteKey()]), tagPayload([
                'name' => 'comida',
            ]))
            ->assertRedirect()
            ->assertInvalid(['name']);
    });

    it('allows the same name on another team', function (): void {
        [$user, $team] = actingMember();
        Tag::factory()->for(Team::factory())->create(['name' => 'Comida']);

        $this->from(route('finance.teams.tags.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.tags.store', ['team' => $team->getRouteKey()]), tagPayload())
            ->assertRedirect();

        expect($team->tags()->where('name', 'Comida')->count())->toBe(1);
    });

    it('rejects an empty payload', function (): void {
        [$user, $team] = actingMember();

        $this->from(route('finance.teams.tags.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.tags.store', ['team' => $team->getRouteKey()]), [])
            ->assertRedirect()
            ->assertInvalid(['name']);
    });

    it('rejects a name that is not a string', function (): void {
        [$user, $team] = actingMember();

        $this->from(route('finance.teams.tags.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.tags.store', ['team' => $team->getRouteKey()]), tagPayload([
                'name' => ['Comida'],
            ]))
            ->assertRedirect()
            ->assertInvalid(['name']);

        expect(Tag::query()->count())->toBe(0);
    });

    it('records the tag creation in the activity log', function (): void {
        [$user, $team] = actingMember();
        clearActivities();

        $this->post(route('finance.teams.tags.store', ['team' => $team->getRouteKey()]), tagPayload())
            ->assertRedirect();

        $activity = activitiesOf($team)->sole();

        expect($activity->event)->toBe('created')
            ->and($activity->causer?->is($user))->toBeTrue()
            ->and($activity->subject?->is(Tag::query()->sole()))->toBeTrue()
            ->and(collect($activity->attribute_changes['attributes'])->sortKeys()->all())->toBe(['is_active' => true, 'name' => 'Comida']);
    });
});

describe('edit', function (): void {
    it('renders the edit tag modal', function (): void {
        [$user, $team] = actingMember();
        $tag = Tag::factory()->for($team)->create();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.tags.edit', [
                'team' => $team->getRouteKey(),
                'tag' => $tag->getKey(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('finance/teams/tags/edit')
                ->where('tag.id', $tag->getKey()));
    });

    it('returns 404 when the tag belongs to another team', function (): void {
        [$user, $team] = actingMember();
        $foreign = Tag::factory()->for(Team::factory())->create();

        $this->get(route('finance.teams.tags.edit', [
            'team' => $team->getRouteKey(),
            'tag' => $foreign->getKey(),
        ]))->assertNotFound();
    });
});

describe('update', function (): void {
    it('updates the tag', function (): void {
        [$user, $team] = actingMember();
        $tag = Tag::factory()->for($team)->create(['name' => 'Old']);

        $this->from(route('finance.teams.tags.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.tags.update', [
                'team' => $team->getRouteKey(),
                'tag' => $tag->getKey(),
            ]), tagPayload([
                'name' => 'New',
            ]))
            ->assertRedirect();

        assertFlashedToast(trans('Saved'));
        expect($tag->fresh()?->name)->toBe('New');
    });

    it('allows keeping the current name', function (): void {
        [$user, $team] = actingMember();
        $tag = Tag::factory()->for($team)->create(['name' => 'Comida']);

        $this->from(route('finance.teams.tags.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.tags.update', [
                'team' => $team->getRouteKey(),
                'tag' => $tag->getKey(),
            ]), tagPayload())
            ->assertRedirect();

        expect($tag->fresh()?->name)->toBe('Comida');
    });

    it('rejects a duplicate name ignoring case', function (): void {
        [$user, $team] = actingMember();
        Tag::factory()->for($team)->create(['name' => 'Comida']);
        $tag = Tag::factory()->for($team)->create(['name' => 'Salidas']);

        $this->from(route('finance.teams.tags.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.tags.update', [
                'team' => $team->getRouteKey(),
                'tag' => $tag->getKey(),
            ]), tagPayload([
                'name' => 'comida',
            ]))
            ->assertRedirect()
            ->assertInvalid(['name']);
    });

    it('returns 404 when updating a tag from another team', function (): void {
        [$user, $team] = actingMember();
        $foreign = Tag::factory()->for(Team::factory())->create();

        $this->patch(route('finance.teams.tags.update', [
            'team' => $team->getRouteKey(),
            'tag' => $foreign->getKey(),
        ]), tagPayload())->assertNotFound();
    });

    it('rejects a name that is not a string', function (): void {
        [$user, $team] = actingMember();
        $tag = Tag::factory()->for($team)->create(['name' => 'Comida']);

        $this->from(route('finance.teams.tags.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.tags.update', [
                'team' => $team->getRouteKey(),
                'tag' => $tag->getKey(),
            ]), tagPayload([
                'name' => ['Salidas'],
            ]))
            ->assertRedirect()
            ->assertInvalid(['name']);

        expect($tag->fresh()?->name)->toBe('Comida');
    });

    it('records the rename in the activity log', function (): void {
        [$user, $team] = actingMember();
        $tag = Tag::factory()->for($team)->create(['name' => 'Old']);
        clearActivities();

        $this->patch(route('finance.teams.tags.update', [
            'team' => $team->getRouteKey(),
            'tag' => $tag->getKey(),
        ]), tagPayload(['name' => 'New']))->assertRedirect();

        $activity = activitiesOf($team)->sole();

        expect($activity->event)->toBe('updated')
            ->and($activity->attribute_changes['old'])->toBe(['name' => 'Old'])
            ->and($activity->attribute_changes['attributes'])->toBe(['name' => 'New']);
    });
});

describe('activate', function (): void {
    it('activates the tag', function (): void {
        [$user, $team] = actingMember();
        $tag = Tag::factory()->inactive()->for($team)->create();

        $this->from(route('finance.teams.tags.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.tags.activate', [
                'team' => $team->getRouteKey(),
                'tag' => $tag->getKey(),
            ]))
            ->assertRedirect();

        assertFlashedToast(trans('Saved'));
        expect($tag->fresh()?->is_active)->toBeTrue();
    });

    it('records the activation in the activity log', function (): void {
        [$user, $team] = actingMember();
        $tag = Tag::factory()->inactive()->for($team)->create();
        clearActivities();

        $this->patch(route('finance.teams.tags.activate', [
            'team' => $team->getRouteKey(),
            'tag' => $tag->getKey(),
        ]))->assertRedirect();

        $activity = activitiesOf($team)->sole();

        expect($activity->event)->toBe('updated')
            ->and($activity->attribute_changes['old'])->toBe(['is_active' => false])
            ->and($activity->attribute_changes['attributes'])->toBe(['is_active' => true]);
    });
});

describe('deactivate', function (): void {
    it('deactivates the tag', function (): void {
        [$user, $team] = actingMember();
        $tag = Tag::factory()->active()->for($team)->create();

        $this->from(route('finance.teams.tags.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.tags.deactivate', [
                'team' => $team->getRouteKey(),
                'tag' => $tag->getKey(),
            ]))
            ->assertRedirect();

        assertFlashedToast(trans('Saved'));
        expect($tag->fresh()?->is_active)->toBeFalse();
    });

    it('records the deactivation in the activity log', function (): void {
        [$user, $team] = actingMember();
        $tag = Tag::factory()->active()->for($team)->create();
        clearActivities();

        $this->patch(route('finance.teams.tags.deactivate', [
            'team' => $team->getRouteKey(),
            'tag' => $tag->getKey(),
        ]))->assertRedirect();

        $activity = activitiesOf($team)->sole();

        expect($activity->event)->toBe('updated')
            ->and($activity->attribute_changes['old'])->toBe(['is_active' => true])
            ->and($activity->attribute_changes['attributes'])->toBe(['is_active' => false]);
    });
});

describe('budgets on the tag form', function (): void {
    /**
     * Create a pro team holding one currency on an active
     * account, which is what a budget may be set in.
     *
     * @return array{0: \App\Modules\Core\Identity\Models\Team, 1: \App\Modules\Core\Catalog\Models\Currency}
     */
    $budgetingTeam = function (bool $pro = true): array {
        [$user, $team] = actingMember();

        if ($pro) {
            subscribeToPro($team);
        }

        $currency = Currency::factory()->active()->fiat()->create(['code' => 'ARS', 'decimal_places' => 2]);
        $account = Account::factory()->active()->for($team)->create();
        $account->currencies()->attach($currency, ['balance' => '0']);

        return [$team, $currency];
    };

    it('offers the held currencies on the create modal', function () use ($budgetingTeam): void {
        [$team, $currency] = $budgetingTeam();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.tags.create', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->has('currencies', 1)
                ->where('currencies.0.code', 'ARS'));
    });

    it('shows no budget fields to a free team', function () use ($budgetingTeam): void {
        [$team] = $budgetingTeam(false);

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.tags.create', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->has('currencies', 0));
    });

    it('opens a budget from this month when the tag is created', function () use ($budgetingTeam): void {
        [$team, $currency] = $budgetingTeam();

        $this->post(route('finance.teams.tags.store', ['team' => $team->getRouteKey()]), tagPayload([
            'budgets' => [$currency->getKey() => '750.00'],
        ]))->assertRedirect();

        $period = BudgetPeriod::query()->sole();

        expect((float) $period->amount)->toBe(750.0)
            ->and($period->from_month->format('Y-m'))->toBe(CarbonImmutable::now()->format('Y-m'))
            ->and($period->until_month)->toBeNull();
    });

    it('creates no budget when the amount is left blank', function () use ($budgetingTeam): void {
        [$team, $currency] = $budgetingTeam();

        $this->post(route('finance.teams.tags.store', ['team' => $team->getRouteKey()]), tagPayload([
            'budgets' => [$currency->getKey() => ''],
        ]))->assertRedirect();

        expect(BudgetPeriod::query()->count())->toBe(0);
    });

    it('ignores budgets submitted by a free team', function () use ($budgetingTeam): void {
        [$team, $currency] = $budgetingTeam(false);

        $this->post(route('finance.teams.tags.store', ['team' => $team->getRouteKey()]), tagPayload([
            'budgets' => [$currency->getKey() => '750.00'],
        ]))->assertRedirect();

        expect(BudgetPeriod::query()->count())->toBe(0);
    });

    it('rejects a currency the team does not hold', function () use ($budgetingTeam): void {
        [$team] = $budgetingTeam();
        $foreign = Currency::factory()->active()->fiat()->create(['code' => 'EUR']);

        $this->from(route('finance.teams.tags.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.tags.store', ['team' => $team->getRouteKey()]), tagPayload([
                'budgets' => [$foreign->getKey() => '750.00'],
            ]))
            ->assertRedirect()
            ->assertInvalid(['budgets']);

        expect(BudgetPeriod::query()->count())->toBe(0);
    });

    it('leaves out a held currency the form did not submit', function () use ($budgetingTeam): void {
        [$team, $currency] = $budgetingTeam();
        $dollar = Currency::factory()->active()->fiat()->create(['code' => 'USD', 'decimal_places' => 2]);
        Account::factory()->active()->for($team)->create()
            ->currencies()
            ->attach($dollar, ['balance' => '0']);

        $this->post(route('finance.teams.tags.store', ['team' => $team->getRouteKey()]), tagPayload([
            'budgets' => [$currency->getKey() => '750.00'],
        ]))->assertRedirect();

        expect(BudgetPeriod::query()->count())->toBe(1)
            ->and(BudgetPeriod::query()->sole()->currency_id)->toBe($currency->getKey());
    });

    it('skips held-currency keys when the payload is not an array', function () use ($budgetingTeam): void {
        [$team] = $budgetingTeam();

        $this->from(route('finance.teams.tags.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.tags.store', ['team' => $team->getRouteKey()]), tagPayload([
                'budgets' => 'nope',
            ]))
            ->assertRedirect()
            ->assertInvalid(['budgets']);
    });

    it('rejects an amount that is not positive', function () use ($budgetingTeam): void {
        [$team, $currency] = $budgetingTeam();

        $this->from(route('finance.teams.tags.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.tags.store', ['team' => $team->getRouteKey()]), tagPayload([
                'budgets' => [$currency->getKey() => '-10'],
            ]))
            ->assertRedirect()
            ->assertInvalid(['budgets.'.$currency->getKey()]);
    });

    it('prefills the edit modal with the running amount', function () use ($budgetingTeam): void {
        [$team, $currency] = $budgetingTeam();
        $tag = Tag::factory()->for($team)->create();
        BudgetPeriod::factory()->running()->for($tag)->for($currency)->create(['amount' => '400']);

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.tags.edit', [
                'team' => $team->getRouteKey(),
                'tag' => $tag->getKey(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('budgets.'.$currency->getKey(), fn (mixed $value): bool => (float) $value === 400.0));
    });

    it('ends the running stretch last month and opens a new one on the new amount', function () use ($budgetingTeam): void {
        [$team, $currency] = $budgetingTeam();
        $tag = Tag::factory()->for($team)->create(['name' => 'Food']);
        $running = BudgetPeriod::factory()->for($tag)->for($currency)->create([
            'amount' => '400',
            'from_month' => CarbonImmutable::now()->subMonths(3),
            'until_month' => null,
        ]);

        $this->patch(route('finance.teams.tags.update', [
            'team' => $team->getRouteKey(),
            'tag' => $tag->getKey(),
        ]), tagPayload([
            'name' => 'Food',
            'budgets' => [$currency->getKey() => '600'],
        ]))->assertRedirect();

        $opened = BudgetPeriod::query()->whereKeyNot($running)->sole();

        expect($running->fresh()?->until_month?->format('Y-m'))->toBe(CarbonImmutable::now()->subMonth()->format('Y-m'))
            ->and((float) $opened->amount)->toBe(600.0)
            ->and($opened->from_month->format('Y-m'))->toBe(CarbonImmutable::now()->format('Y-m'));
    });

    it('carries the warning threshold onto the new stretch', function () use ($budgetingTeam): void {
        [$team, $currency] = $budgetingTeam();
        $tag = Tag::factory()->for($team)->create(['name' => 'Food']);
        $running = BudgetPeriod::factory()->withWarningThreshold(85)->for($tag)->for($currency)->create([
            'amount' => '400',
            'from_month' => CarbonImmutable::now()->subMonths(3),
            'until_month' => null,
        ]);

        $this->patch(route('finance.teams.tags.update', [
            'team' => $team->getRouteKey(),
            'tag' => $tag->getKey(),
        ]), tagPayload([
            'name' => 'Food',
            'budgets' => [$currency->getKey() => '600'],
        ]))->assertRedirect();

        expect(BudgetPeriod::query()->whereKeyNot($running)->sole()->warning_threshold)->toBe(85);
    });

    it('changes the amount in place when the stretch started this month', function () use ($budgetingTeam): void {
        [$team, $currency] = $budgetingTeam();
        $tag = Tag::factory()->for($team)->create(['name' => 'Food']);
        $running = BudgetPeriod::factory()->running()->for($tag)->for($currency)->create(['amount' => '400']);

        $this->patch(route('finance.teams.tags.update', [
            'team' => $team->getRouteKey(),
            'tag' => $tag->getKey(),
        ]), tagPayload([
            'name' => 'Food',
            'budgets' => [$currency->getKey() => '600'],
        ]))->assertRedirect();

        expect(BudgetPeriod::query()->count())->toBe(1)
            ->and((float) $running->fresh()?->amount)->toBe(600.0);
    });

    it('stops the budget at the end of last month when the amount is cleared', function () use ($budgetingTeam): void {
        [$team, $currency] = $budgetingTeam();
        $tag = Tag::factory()->for($team)->create(['name' => 'Food']);
        $running = BudgetPeriod::factory()->for($tag)->for($currency)->create([
            'amount' => '400',
            'from_month' => CarbonImmutable::now()->subMonths(3),
            'until_month' => null,
        ]);

        $this->patch(route('finance.teams.tags.update', [
            'team' => $team->getRouteKey(),
            'tag' => $tag->getKey(),
        ]), tagPayload([
            'name' => 'Food',
            'budgets' => [$currency->getKey() => ''],
        ]))->assertRedirect();

        expect($running->fresh()?->until_month?->format('Y-m'))
            ->toBe(CarbonImmutable::now()->subMonth()->format('Y-m'));
    });

    it('leaves a currency the form did not carry untouched', function () use ($budgetingTeam): void {
        [$team, $currency] = $budgetingTeam();
        $tag = Tag::factory()->for($team)->create(['name' => 'Food']);
        BudgetPeriod::factory()->running()->for($tag)->for($currency)->create(['amount' => '400']);

        $this->patch(route('finance.teams.tags.update', [
            'team' => $team->getRouteKey(),
            'tag' => $tag->getKey(),
        ]), tagPayload(['name' => 'Food']))->assertRedirect();

        expect((float) BudgetPeriod::query()->sole()->amount)->toBe(400.0);
    });
});
