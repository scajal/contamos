<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\RecurringRule\Frequency;
use App\Modules\Core\Finance\Enums\RecurringRule\Status;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Feature tests for \App\Modules\Core\Finance\Http\Controllers\Team\RecurringRuleController.
 */

/**
 * Give the team an active account holding an active currency, the
 * pair every rule is written against.
 *
 * @return array{0: \App\Modules\Core\Finance\Models\Team\Account, 1: \App\Modules\Core\Catalog\Models\Currency}
 */
function ledgerFor(Team $team, string $balance = '0'): array
{
    $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
    $account = Account::factory()->bank()->active()->for($team)->create();
    $account->currencies()->attach($currency, ['balance' => $balance]);

    return [$account, $currency];
}

/**
 * The payload the rule form submits.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function rulePayload(array $overrides = []): array
{
    $type = TransactionType::tryFrom(is_string($overrides['type'] ?? null) ? $overrides['type'] : TransactionType::Expense->value);
    $categories = $type instanceof TransactionType ? Category::casesFor($type) : [];

    return [
        'amount' => '25.00',
        // A rule names the category it stamps on every
        // transaction it generates.
        ...($categories === [] ? [] : ['category' => $categories[0]->value]),
        'description' => 'Rent',
        'ends_on' => null,
        'frequency' => Frequency::Monthly->value,
        'interval' => 1,
        'starts_on' => CarbonImmutable::now()->addDay()->toDateString(),
        'time_of_day' => '09:00',
        'timezone' => 'America/Argentina/Buenos_Aires',
        'type' => 'expense',
        ...$overrides,
    ];
}

describe('index', function (): void {
    it('renders a deferred list of the team\'s recurring rules', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        [$account, $currency] = ledgerFor($team);
        $rule = RecurringRule::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $this->get(route('finance.teams.recurring-rules.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('finance/teams/recurring-rules/index')
                ->where('canRecur', true)
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('rules.data', 1)
                    ->where('rules.data.0.id', $rule->getKey())
                    ->where('rules.data.0.status', Status::Active->value)
                    ->has('rules.data.0.next_due_date')));
    });

    it('sorts the rules by when each one fires next', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        [$account, $currency] = ledgerFor($team);

        $later = RecurringRule::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'starts_on' => CarbonImmutable::now()->addMonth()->toDateString(),
            'team_id' => $team->getKey(),
        ]);
        $sooner = RecurringRule::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'starts_on' => CarbonImmutable::now()->addDay()->toDateString(),
            'team_id' => $team->getKey(),
        ]);

        $this->get(route('finance.teams.recurring-rules.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->where('rules.data.0.id', $sooner->getKey())
                    ->where('rules.data.1.id', $later->getKey())));
    });

    it('lists the rules of a free team read-only', function (): void {
        [$user, $team] = actingMember();
        [$account, $currency] = ledgerFor($team);
        RecurringRule::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
        ]);

        $this->get(route('finance.teams.recurring-rules.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('canRecur', false)
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('rules.data', 1)));
    });

    it('excludes rules belonging to another team', function (): void {
        [$user, $team] = actingMember();
        RecurringRule::factory()->expense()->create();

        $this->get(route('finance.teams.recurring-rules.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('rules.data', 0)));
    });
});

describe('create', function (): void {
    it('renders the rule form', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        ledgerFor($team);

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.recurring-rules.create', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('finance/teams/recurring-rules/create')
                ->has('accounts', 1)
                ->has('tags'));
    });

    it('sends a free team to the upgrade prompt', function (): void {
        [$user, $team] = actingMember();

        $this->get(route('finance.teams.recurring-rules.create', ['team' => $team->getRouteKey()]))
            ->assertRedirect();
    });
    it('offers the categories each type carries', function (): void {
        [, $team] = actingMember();
        subscribeToPro($team);
        ledgerFor($team);

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.recurring-rules.create', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->has('categories.expense', count(Category::casesFor(TransactionType::Expense)))
                ->has('categories.income', count(Category::casesFor(TransactionType::Income)))
                ->where('categories.expense.0.value', Category::Housing->value)
                ->etc());
    });
});

describe('store', function (): void {
    it('creates a rule with its tags', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        [$account, $currency] = ledgerFor($team);
        $tag = Tag::factory()->active()->for($team)->create();

        $this->post(route('finance.teams.recurring-rules.store', ['team' => $team->getRouteKey()]), rulePayload([
            $account->getForeignKey() => $account->getKey(),
            $currency->getForeignKey() => $currency->getKey(),
            'tag_ids' => [$tag->getKey()],
        ]))->assertRedirect();

        assertFlashedToast(trans('Saved'));

        $rule = RecurringRule::query()->sole();

        expect($rule->frequency)->toBe(Frequency::Monthly)
            ->and($rule->interval)->toBe(1)
            ->and($rule->time_of_day)->toBe('09:00:00')
            ->and($rule->timezone)->toBe('America/Argentina/Buenos_Aires')
            ->and($rule->is_active)->toBeTrue()
            ->and($rule->user?->is($user))->toBeTrue()
            ->and($rule->tags->modelKeys())->toBe([$tag->getKey()]);
    });

    it('rejects a start date in the past', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        [$account, $currency] = ledgerFor($team);

        $this->post(route('finance.teams.recurring-rules.store', ['team' => $team->getRouteKey()]), rulePayload([
            $account->getForeignKey() => $account->getKey(),
            $currency->getForeignKey() => $currency->getKey(),
            'starts_on' => CarbonImmutable::now()->subDay()->toDateString(),
        ]))->assertSessionHasErrors('starts_on');

        expect(RecurringRule::query()->count())->toBe(0);
    });

    it('skips the inactive-tag check when a key is not a string', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        [$account, $currency] = ledgerFor($team);

        $this->post(route('finance.teams.recurring-rules.store', ['team' => $team->getRouteKey()]), rulePayload([
            $account->getForeignKey() => $account->getKey(),
            $currency->getForeignKey() => $currency->getKey(),
            'tag_ids' => [1],
        ]))->assertSessionHasErrors('tag_ids.0');

        expect(RecurringRule::query()->count())->toBe(0);
    });

    it('rejects an end date before the start date', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        [$account, $currency] = ledgerFor($team);

        $this->post(route('finance.teams.recurring-rules.store', ['team' => $team->getRouteKey()]), rulePayload([
            $account->getForeignKey() => $account->getKey(),
            $currency->getForeignKey() => $currency->getKey(),
            'starts_on' => CarbonImmutable::now()->addWeek()->toDateString(),
            'ends_on' => CarbonImmutable::now()->addDay()->toDateString(),
        ]))->assertSessionHasErrors('ends_on');
    });

    it('rejects an interval outside 1 to 99', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        [$account, $currency] = ledgerFor($team);

        $this->post(route('finance.teams.recurring-rules.store', ['team' => $team->getRouteKey()]), rulePayload([
            $account->getForeignKey() => $account->getKey(),
            $currency->getForeignKey() => $currency->getKey(),
            'interval' => 100,
        ]))->assertSessionHasErrors('interval');
    });

    it('rejects an unknown timezone', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        [$account, $currency] = ledgerFor($team);

        $this->post(route('finance.teams.recurring-rules.store', ['team' => $team->getRouteKey()]), rulePayload([
            $account->getForeignKey() => $account->getKey(),
            $currency->getForeignKey() => $currency->getKey(),
            'timezone' => 'Mars/Olympus',
        ]))->assertSessionHasErrors('timezone');
    });

    it('allows a second identical rule on the same account', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        [$account, $currency] = ledgerFor($team);

        $payload = rulePayload([
            $account->getForeignKey() => $account->getKey(),
            $currency->getForeignKey() => $currency->getKey(),
        ]);

        $this->post(route('finance.teams.recurring-rules.store', ['team' => $team->getRouteKey()]), $payload)->assertRedirect();
        $this->post(route('finance.teams.recurring-rules.store', ['team' => $team->getRouteKey()]), $payload)->assertRedirect();

        expect(RecurringRule::query()->count())->toBe(2);
    });

    it('rejects a free team', function (): void {
        [$user, $team] = actingMember();
        [$account, $currency] = ledgerFor($team);

        $this->post(route('finance.teams.recurring-rules.store', ['team' => $team->getRouteKey()]), rulePayload([
            $account->getForeignKey() => $account->getKey(),
            $currency->getForeignKey() => $currency->getKey(),
        ]))->assertRedirect();

        expect(RecurringRule::query()->count())->toBe(0);
    });
    it('records the category the rule type offers', function (): void {
        [, $team] = actingMember();
        subscribeToPro($team);
        [$account, $currency] = ledgerFor($team);

        $this->post(route('finance.teams.recurring-rules.store', ['team' => $team->getRouteKey()]), rulePayload([
            $account->getForeignKey() => $account->getKey(),
            $currency->getForeignKey() => $currency->getKey(),
            'category' => Category::Salary->value,
            'type' => TransactionType::Income->value,
        ]))->assertRedirect();

        expect(RecurringRule::query()->sole()->category)->toBe(Category::Salary);
    });

    it('rejects a rule category that belongs to the other type', function (): void {
        [, $team] = actingMember();
        subscribeToPro($team);
        [$account, $currency] = ledgerFor($team);

        $this->post(route('finance.teams.recurring-rules.store', ['team' => $team->getRouteKey()]), rulePayload([
            $account->getForeignKey() => $account->getKey(),
            $currency->getForeignKey() => $currency->getKey(),
            'category' => Category::Salary->value,
            'type' => TransactionType::Expense->value,
        ]))->assertSessionHasErrors('category');

        expect(RecurringRule::query()->count())->toBe(0);
    });

    it('rejects a rule created without a category', function (): void {
        [, $team] = actingMember();
        subscribeToPro($team);
        [$account, $currency] = ledgerFor($team);

        $payload = rulePayload([
            $account->getForeignKey() => $account->getKey(),
            $currency->getForeignKey() => $currency->getKey(),
        ]);
        unset($payload['category']);

        $this->post(route('finance.teams.recurring-rules.store', ['team' => $team->getRouteKey()]), $payload)
            ->assertSessionHasErrors('category');

        expect(RecurringRule::query()->count())->toBe(0);
    });
});

describe('edit', function (): void {
    it('renders the rule in the form', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        [$account, $currency] = ledgerFor($team);
        $rule = RecurringRule::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
        ]);

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.recurring-rules.edit', [
                'team' => $team->getRouteKey(),
                'recurringRule' => $rule->getRouteKey(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('finance/teams/recurring-rules/edit')
                ->where('rule.id', $rule->getKey()));
    });
});

describe('update', function (): void {
    it('applies the new schedule from the next occurrence and clears the failure', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        [$account, $currency] = ledgerFor($team);
        $rule = RecurringRule::factory()->expense()->failed()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'frequency' => Frequency::Monthly,
            'last_generated_due_date' => '2026-02-15',
            'starts_on' => '2026-01-15',
            'team_id' => $team->getKey(),
        ]);

        $this->patch(route('finance.teams.recurring-rules.update', [
            'team' => $team->getRouteKey(),
            'recurringRule' => $rule->getRouteKey(),
        ]), rulePayload([
            $account->getForeignKey() => $account->getKey(),
            $currency->getForeignKey() => $currency->getKey(),
            'amount' => '99.00',
            'frequency' => Frequency::Weekly->value,
            'interval' => 2,
            'starts_on' => '2026-01-15',
        ]))->assertRedirect();

        assertFlashedToast(trans('Saved'));

        $rule->refresh();

        expect((float) $rule->amount)->toBe(99.0)
            ->and($rule->frequency)->toBe(Frequency::Weekly)
            ->and($rule->failure_reason)->toBeNull()
            ->and($rule->failed_at)->toBeNull()
            ->and($rule->last_generated_due_date?->toDateString())->toBe('2026-02-15')
            ->and($rule->next_due_date?->toDateString())->toBe('2026-02-26');
    });

    it('keeps a tag that was deactivated after it was chosen', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        [$account, $currency] = ledgerFor($team);
        $tag = Tag::factory()->active()->for($team)->create();
        $rule = RecurringRule::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
        ]);
        $rule->attachTags([$tag->getKey()]);
        $tag->deactivate();

        $this->patch(route('finance.teams.recurring-rules.update', [
            'team' => $team->getRouteKey(),
            'recurringRule' => $rule->getRouteKey(),
        ]), rulePayload([
            $account->getForeignKey() => $account->getKey(),
            $currency->getForeignKey() => $currency->getKey(),
            'starts_on' => $rule->starts_on->toDateString(),
            'tag_ids' => [$tag->getKey()],
        ]))->assertRedirect();

        expect($rule->refresh()->tags->modelKeys())->toBe([$tag->getKey()]);
    });

    it('rejects moving the start date into the past', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        [$account, $currency] = ledgerFor($team);
        $rule = RecurringRule::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'starts_on' => CarbonImmutable::now()->addWeek()->toDateString(),
            'team_id' => $team->getKey(),
        ]);

        $this->patch(route('finance.teams.recurring-rules.update', [
            'team' => $team->getRouteKey(),
            'recurringRule' => $rule->getRouteKey(),
        ]), rulePayload([
            $account->getForeignKey() => $account->getKey(),
            $currency->getForeignKey() => $currency->getKey(),
            'starts_on' => CarbonImmutable::now()->subDay()->toDateString(),
        ]))->assertSessionHasErrors('starts_on');

        expect($rule->fresh()?->starts_on?->toDateString())
            ->toBe($rule->starts_on->toDateString());
    });

    it('allows moving the start date to another day from today on', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        [$account, $currency] = ledgerFor($team);
        $rule = RecurringRule::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'starts_on' => CarbonImmutable::now()->addWeek()->toDateString(),
            'team_id' => $team->getKey(),
        ]);
        $startsOn = CarbonImmutable::now()->addDays(3)->toDateString();

        $this->patch(route('finance.teams.recurring-rules.update', [
            'team' => $team->getRouteKey(),
            'recurringRule' => $rule->getRouteKey(),
        ]), rulePayload([
            $account->getForeignKey() => $account->getKey(),
            $currency->getForeignKey() => $currency->getKey(),
            'starts_on' => $startsOn,
        ]))->assertRedirect();

        expect($rule->fresh()?->starts_on?->toDateString())->toBe($startsOn);
    });

    it('rejects a newly chosen tag that is inactive', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        [$account, $currency] = ledgerFor($team);
        $tag = Tag::factory()->for($team)->create(['is_active' => false]);
        $rule = RecurringRule::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
        ]);

        $this->patch(route('finance.teams.recurring-rules.update', [
            'team' => $team->getRouteKey(),
            'recurringRule' => $rule->getRouteKey(),
        ]), rulePayload([
            $account->getForeignKey() => $account->getKey(),
            $currency->getForeignKey() => $currency->getKey(),
            'starts_on' => $rule->starts_on->toDateString(),
            'tag_ids' => [$tag->getKey()],
        ]))->assertSessionHasErrors('tag_ids.0');
    });

    it('rejects a free team', function (): void {
        [$user, $team] = actingMember();
        [$account, $currency] = ledgerFor($team);
        $rule = RecurringRule::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
        ]);

        $this->patch(route('finance.teams.recurring-rules.update', [
            'team' => $team->getRouteKey(),
            'recurringRule' => $rule->getRouteKey(),
        ]), rulePayload([
            $account->getForeignKey() => $account->getKey(),
            $currency->getForeignKey() => $currency->getKey(),
            'amount' => '99.00',
        ]))->assertRedirect();

        expect((float) $rule->refresh()->amount)->not->toBe(99.0);
    });
    it('recategorizes the rule', function (): void {
        [, $team] = actingMember();
        subscribeToPro($team);
        [$account, $currency] = ledgerFor($team);
        $rule = RecurringRule::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'category' => Category::Food,
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
        ]);

        $this->patch(route('finance.teams.recurring-rules.update', [
            'team' => $team->getRouteKey(),
            'recurringRule' => $rule->getKey(),
        ]), rulePayload([
            $account->getForeignKey() => $account->getKey(),
            $currency->getForeignKey() => $currency->getKey(),
            'category' => Category::Transport->value,
        ]))->assertRedirect();

        expect($rule->refresh()->category)->toBe(Category::Transport);
    });

    it('rejects a rule category that does not belong to the type it is saved with', function (): void {
        [, $team] = actingMember();
        subscribeToPro($team);
        [$account, $currency] = ledgerFor($team);
        $rule = RecurringRule::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'category' => Category::Food,
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
        ]);

        $this->patch(route('finance.teams.recurring-rules.update', [
            'team' => $team->getRouteKey(),
            'recurringRule' => $rule->getKey(),
        ]), rulePayload([
            $account->getForeignKey() => $account->getKey(),
            $currency->getForeignKey() => $currency->getKey(),
            'category' => Category::Food->value,
            'type' => TransactionType::Income->value,
        ]))->assertSessionHasErrors('category');

        expect($rule->refresh()->category)->toBe(Category::Food);
    });
});

describe('pause', function (): void {
    it('suspends the rule without clearing its failure', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        [$account, $currency] = ledgerFor($team);
        $rule = RecurringRule::factory()->expense()->failed('No rate')->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
        ]);

        $this->patch(route('finance.teams.recurring-rules.pause', [
            'team' => $team->getRouteKey(),
            'recurringRule' => $rule->getRouteKey(),
        ]))->assertRedirect();

        assertFlashedToast(trans('Paused'));

        expect($rule->refresh()->is_active)->toBeFalse()
            ->and($rule->failure_reason)->toBe('No rate')
            ->and($rule->status)->toBe(Status::Paused);
    });

    it('rejects a free team', function (): void {
        [$user, $team] = actingMember();
        [$account, $currency] = ledgerFor($team);
        $rule = RecurringRule::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
        ]);

        $this->patch(route('finance.teams.recurring-rules.pause', [
            'team' => $team->getRouteKey(),
            'recurringRule' => $rule->getRouteKey(),
        ]))->assertRedirect();

        expect($rule->refresh()->is_active)->toBeTrue();
    });
});

describe('resume', function (): void {
    it('resumes from the next due date without backfilling the paused stretch', function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-05-20 10:00:00'));

        [$user, $team] = actingMember();
        subscribeToPro($team);
        [$account, $currency] = ledgerFor($team);
        $rule = RecurringRule::factory()->expense()->paused()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'frequency' => Frequency::Monthly,
            'last_generated_due_date' => '2026-02-15',
            'starts_on' => '2026-01-15',
            'team_id' => $team->getKey(),
        ]);

        $this->patch(route('finance.teams.recurring-rules.resume', [
            'team' => $team->getRouteKey(),
            'recurringRule' => $rule->getRouteKey(),
        ]))->assertRedirect();

        assertFlashedToast(trans('Resumed'));

        $rule->refresh();

        expect($rule->is_active)->toBeTrue()
            ->and($rule->next_due_date?->toDateString())->toBe('2026-06-15');
    });
});

describe('activity log', function (): void {
    it('records creating, editing, pausing and deleting a rule', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        [$account, $currency] = ledgerFor($team);

        $payload = rulePayload([
            $account->getForeignKey() => $account->getKey(),
            $currency->getForeignKey() => $currency->getKey(),
        ]);

        $this->post(route('finance.teams.recurring-rules.store', ['team' => $team->getRouteKey()]), $payload);

        $rule = RecurringRule::query()->sole();

        $this->patch(route('finance.teams.recurring-rules.update', [
            'team' => $team->getRouteKey(),
            'recurringRule' => $rule->getRouteKey(),
        ]), [...$payload, 'amount' => '99.00']);

        $this->patch(route('finance.teams.recurring-rules.pause', [
            'team' => $team->getRouteKey(),
            'recurringRule' => $rule->getRouteKey(),
        ]));

        $this->patch(route('finance.teams.recurring-rules.resume', [
            'team' => $team->getRouteKey(),
            'recurringRule' => $rule->getRouteKey(),
        ]));

        $this->delete(route('finance.teams.recurring-rules.destroy', [
            'team' => $team->getRouteKey(),
            'recurringRule' => $rule->getRouteKey(),
        ]));

        $activities = activitiesOf($team)
            ->where('subject_type', RecurringRule::class)
            ->values();

        // Every write on the rule is attributed to the member who made
        // it, from the create through the edit, the pause, the resume,
        // and the delete.
        $flags = $activities
            ->filter(fn ($activity): bool => array_key_exists('is_active', $activity->attribute_changes['attributes'] ?? []))
            ->pluck('attribute_changes.attributes.is_active')
            ->values();

        expect($activities->first()?->event)->toBe('created')
            ->and($activities->last()?->event)->toBe('deleted')
            ->and($activities->pluck('event')->unique()->values()->all())->toBe(['created', 'updated', 'deleted'])
            ->and($activities->pluck('attribute_changes.attributes.amount')->filter()->last())->toBe('99.000000000000000000')
            ->and($flags->slice(-2)->values()->all())->toBe([false, true])
            ->and($activities->first()?->causer?->is($user))->toBeTrue();
    });
});

describe('destroy', function (): void {
    it('deletes the rule and keeps the transactions it generated', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        [$account, $currency] = ledgerFor($team, '125');
        $rule = RecurringRule::factory()->income()->create([
            'account_id' => $account->getKey(),
            'amount' => '25.00',
            'currency_id' => $currency->getKey(),
            'frequency' => Frequency::Monthly,
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);
        $transaction = Transaction::factory()->income()->create([
            'account_id' => $account->getKey(),
            'amount' => '25.00',
            'currency_id' => $currency->getKey(),
            'due_date' => $rule->starts_on,
            'recurring_rule_id' => $rule->getKey(),
            'team_id' => $team->getKey(),
        ]);

        $this->from(route('finance.teams.recurring-rules.index', ['team' => $team->getRouteKey()]))
            ->delete(route('finance.teams.recurring-rules.destroy', [
                'team' => $team->getRouteKey(),
                'recurringRule' => $rule->getRouteKey(),
            ]))
            ->assertRedirect();

        assertFlashedToast(trans('Deleted'));

        $transaction->refresh();

        expect(RecurringRule::query()->count())->toBe(0)
            ->and($transaction->exists)->toBeTrue()
            ->and($transaction->recurring_rule_id)->toBeNull()
            ->and($transaction->due_date)->toBeNull()
            ->and((float) $account->currencies()->first()?->pivot->balance)->toBe(125.0);
    });

    it('rejects a rule belonging to another team', function (): void {
        [$user, $team] = actingMember();
        $rule = RecurringRule::factory()->expense()->create();

        $this->delete(route('finance.teams.recurring-rules.destroy', [
            'team' => $team->getRouteKey(),
            'recurringRule' => $rule->getRouteKey(),
        ]))->assertNotFound();

        expect(RecurringRule::query()->count())->toBe(1);
    });
});

describe('undo', function (): void {
    it('deletes the rule and reverts the balance of the transaction it generated', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        [$account, $currency] = ledgerFor($team, '125');
        $rule = RecurringRule::factory()->income()->create([
            'account_id' => $account->getKey(),
            'amount' => '25.00',
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);
        $transaction = Transaction::factory()->income()->create([
            'account_id' => $account->getKey(),
            'amount' => '25.00',
            'currency_id' => $currency->getKey(),
            'due_date' => $rule->starts_on,
            'recurring_rule_id' => $rule->getKey(),
            'team_id' => $team->getKey(),
        ]);

        $this->delete(route('finance.teams.recurring-rules.undo', [
            'team' => $team->getRouteKey(),
            'recurringRule' => $rule->getRouteKey(),
        ]))->assertRedirect();

        expect(RecurringRule::query()->count())->toBe(0)
            ->and(Transaction::query()->whereKey($transaction->getKey())->exists())->toBeFalse()
            ->and((float) $account->currencies()->first()?->pivot->balance)->toBe(100.0);
    });
});
