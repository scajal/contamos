<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Catalog\Models\Currency\Quote;
use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetAlertKind;
use App\Modules\Core\Finance\Enums\RecurringRule\FailureReason;
use App\Modules\Core\Finance\Enums\RecurringRule\Frequency;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Finance\Notifications\Team\BudgetAlertNotification;
use App\Modules\Core\Finance\Notifications\Team\RecurringRuleFailedNotification;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

/**
 * Feature tests for \App\Modules\Core\Finance\Services\RecurringRule\Commands\GenerateRecurringTransactionsCommand.
 */

/**
 * A Pro team with an active account, an active currency quoted every
 * day since the given date, and a rule ready to generate from it.
 *
 * @param  array<string, mixed>  $attributes
 * @return array{0: \App\Modules\Core\Finance\Models\Team\RecurringRule, 1: \App\Modules\Core\Finance\Models\Team\Account, 2: \App\Modules\Core\Catalog\Models\Currency}
 */
function dueRule(array $attributes = [], bool $pro = true, string $balance = '0'): array
{
    $team = Team::factory()->create();

    if ($pro) {
        subscribeToPro($team);
    }

    $user = User::factory()->create();
    $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
    $account = Account::factory()->bank()->active()->for($team)->create();
    $account->currencies()->attach($currency, ['balance' => $balance]);

    Quote::factory()->for($currency)->create([
        'observed_at' => CarbonImmutable::now()->subYear(),
        'rate' => '40',
    ]);

    $rule = RecurringRule::factory()->expense()->create([
        'account_id' => $account->getKey(),
        'amount' => '25.00',
        'currency_id' => $currency->getKey(),
        'decimal_places' => 2,
        'frequency' => Frequency::Monthly,
        'interval' => 1,
        'team_id' => $team->getKey(),
        'time_of_day' => '09:00:00',
        'timezone' => 'UTC',
        'user_id' => $user->getKey(),
        ...$attributes,
    ]);

    return [$rule, $account, $currency];
}

it('generates the occurrence of a due rule', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-05-20 10:00:00'));

    [$rule, $account, $currency] = dueRule(['starts_on' => '2026-05-20'], balance: '100');

    $this->artisan('finance:generate-recurring-transactions')->assertSuccessful();

    $transaction = Transaction::query()->sole();

    expect($transaction->occurred_at->toIso8601String())->toBe('2026-05-20T09:00:00+00:00')
        ->and($transaction->due_date?->toDateString())->toBe('2026-05-20')
        ->and($transaction->recurring_rule_id)->toBe($rule->getKey())
        ->and((float) $transaction->quote)->toBe(40.0)
        ->and($transaction->user?->is($rule->user))->toBeTrue()
        ->and((float) $account->currencies()->first()?->pivot->balance)->toBe(75.0)
        ->and($rule->refresh()->last_generated_due_date?->toDateString())->toBe('2026-05-20');
});

it('carries the rule\'s category onto the occurrence', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-05-20 10:00:00'));

    dueRule(['category' => Category::Transport, 'starts_on' => '2026-05-20']);

    $this->artisan('finance:generate-recurring-transactions')->assertSuccessful();

    expect(Transaction::query()->sole()->category)->toBe(Category::Transport);
});

it('leaves the occurrences it already generated on the category they were recorded with', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-05-20 10:00:00'));

    [$rule] = dueRule(['category' => Category::Transport, 'starts_on' => '2026-05-20']);

    $this->artisan('finance:generate-recurring-transactions')->assertSuccessful();

    $rule->update(['category' => Category::Groceries]);

    $this->travelTo(CarbonImmutable::parse('2026-06-20 10:00:00'));

    $this->artisan('finance:generate-recurring-transactions')->assertSuccessful();

    expect(Transaction::query()->orderBy('occurred_at')->pluck('category')->all())
        ->toBe([Category::Transport, Category::Groceries]);
});

it('carries the rule\'s tags onto the occurrence', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-05-20 10:00:00'));

    [$rule, $account, $currency] = dueRule(['starts_on' => '2026-05-20']);
    $tag = Tag::factory()->active()->create(['team_id' => $rule->team_id]);
    $rule->attachTags([$tag->getKey()]);

    $this->artisan('finance:generate-recurring-transactions')->assertSuccessful();

    expect(Transaction::query()->sole()->tags->modelKeys())->toBe([$tag->getKey()]);
});

it('creates nothing before the due instant', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-05-20 08:00:00'));

    dueRule(['starts_on' => '2026-05-20']);

    $this->artisan('finance:generate-recurring-transactions')->assertSuccessful();

    expect(Transaction::query()->count())->toBe(0);
});

it('creates every missed occurrence with its own due instant', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-05-20 10:00:00'));

    dueRule(['starts_on' => '2026-02-20', 'last_generated_due_date' => '2026-02-20']);

    $this->artisan('finance:generate-recurring-transactions')->assertSuccessful();

    expect(Transaction::query()->orderBy('due_date')->pluck('due_date')->map(fn ($date): string => $date->toDateString())->all())
        ->toBe(['2026-03-20', '2026-04-20', '2026-05-20']);
});

it('creates nothing on a second run', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-05-20 10:00:00'));

    dueRule(['starts_on' => '2026-05-20']);

    $this->artisan('finance:generate-recurring-transactions')->assertSuccessful();
    $this->artisan('finance:generate-recurring-transactions')->assertSuccessful();

    expect(Transaction::query()->count())->toBe(1);
});

it('stops at an inclusive end date', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-05-20 10:00:00'));

    dueRule(['starts_on' => '2026-03-20', 'ends_on' => '2026-04-20']);

    $this->artisan('finance:generate-recurring-transactions')->assertSuccessful();

    expect(Transaction::query()->orderBy('due_date')->pluck('due_date')->map(fn ($date): string => $date->toDateString())->all())
        ->toBe(['2026-03-20', '2026-04-20']);
});

it('skips a paused rule without backfilling it once it resumes', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-05-20 10:00:00'));

    [$rule] = dueRule(['is_active' => false, 'last_generated_due_date' => '2026-02-20', 'starts_on' => '2026-02-20']);

    $this->artisan('finance:generate-recurring-transactions')->assertSuccessful();

    expect(Transaction::query()->count())->toBe(0);

    $rule->refresh()->activate();

    $this->artisan('finance:generate-recurring-transactions')->assertSuccessful();

    expect(Transaction::query()->pluck('due_date')->map(fn ($date): string => $date->toDateString())->all())
        ->toBe(['2026-05-20']);
});

it('skips a free team without backfilling it once it upgrades', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-05-20 10:00:00'));

    [$rule] = dueRule(['last_generated_due_date' => '2026-02-20', 'starts_on' => '2026-02-20'], pro: false);

    $this->artisan('finance:generate-recurring-transactions')->assertSuccessful();

    expect(Transaction::query()->count())->toBe(0);

    subscribeToPro($rule->team);

    $this->artisan('finance:generate-recurring-transactions')->assertSuccessful();

    expect(Transaction::query()->pluck('due_date')->map(fn ($date): string => $date->toDateString())->all())
        ->toBe(['2026-05-20']);
});

it('records a failure when the currency has no rate for the due instant', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-05-20 10:00:00'));

    [$rule, $account, $currency] = dueRule(['starts_on' => '2026-05-20']);
    $currency->quotes()->delete();

    $this->artisan('finance:generate-recurring-transactions')->assertSuccessful();

    $rule->refresh();

    expect(Transaction::query()->count())->toBe(0)
        ->and($rule->failure_reason)->toBe(trans('There is no rate for the due date.'))
        ->and($rule->failed_at)->not->toBeNull()
        ->and($rule->is_active)->toBeTrue();

    Quote::factory()->for($currency)->create(['observed_at' => CarbonImmutable::parse('2026-05-19'), 'rate' => '41']);

    $this->artisan('finance:generate-recurring-transactions')->assertSuccessful();

    $rule->refresh();

    expect(Transaction::query()->sole()->due_date?->toDateString())->toBe('2026-05-20')
        ->and($rule->failure_reason)->toBeNull()
        ->and($rule->failed_at)->toBeNull();
});

it('records a failure when the account is inactive', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-05-20 10:00:00'));

    [$rule, $account] = dueRule(['starts_on' => '2026-05-20']);
    $account->deactivate();

    $this->artisan('finance:generate-recurring-transactions')->assertSuccessful();

    expect(Transaction::query()->count())->toBe(0)
        ->and($rule->refresh()->failure_reason)->toBe(trans('The account is inactive.'));

    $account->activate();

    $this->artisan('finance:generate-recurring-transactions')->assertSuccessful();

    expect(Transaction::query()->count())->toBe(1)
        ->and($rule->refresh()->failure_reason)->toBeNull();
});

it('records a failure when the currency was removed from the account', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-05-20 10:00:00'));

    [$rule, $account, $currency] = dueRule(['starts_on' => '2026-05-20']);
    $account->currencies()->detach($currency);

    $this->artisan('finance:generate-recurring-transactions')->assertSuccessful();

    expect(Transaction::query()->count())->toBe(0)
        ->and($rule->refresh()->failure_reason)->toBe(trans('The currency is no longer on the account.'));
});

it('emails the owner and admins once while a rule stays failed', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-05-20 10:00:00'));

    [$rule, $account, $currency] = dueRule(['description' => 'Rent', 'starts_on' => '2026-05-20']);
    $owner = memberOf($rule->team, Role::Owner);
    $admin = memberOf($rule->team, Role::Admin);
    $member = memberOf($rule->team, Role::Member);
    $account->deactivate();
    Notification::fake();

    $this->artisan('finance:generate-recurring-transactions')->assertSuccessful();

    Notification::assertSentTo(
        [$owner, $admin],
        RecurringRuleFailedNotification::class,
        fn (RecurringRuleFailedNotification $notification): bool => $notification->team->is($rule->team)
            && $notification->rule->is($rule)
            && $notification->reason === FailureReason::InactiveAccount,
    );
    Notification::assertNotSentTo($member, RecurringRuleFailedNotification::class);

    // A later run that fails for another reason stays quiet.
    $account->activate();
    $account->currencies()->detach($currency);

    $this->artisan('finance:generate-recurring-transactions')->assertSuccessful();

    expect($rule->refresh()->failure_reason)->toBe(trans('The currency is no longer on the account.'));

    Notification::assertSentToTimes($owner, RecurringRuleFailedNotification::class, 1);
    Notification::assertSentToTimes($admin, RecurringRuleFailedNotification::class, 1);
});

it('emails again when a rule fails after its failure was cleared', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-05-20 10:00:00'));

    [$rule, $account] = dueRule(['starts_on' => '2026-05-20']);
    $owner = memberOf($rule->team, Role::Owner);
    $account->deactivate();
    Notification::fake();

    $this->artisan('finance:generate-recurring-transactions')->assertSuccessful();

    $account->activate();

    $this->artisan('finance:generate-recurring-transactions')->assertSuccessful();

    expect($rule->refresh()->failure_reason)->toBeNull();

    $account->deactivate();
    $this->travelTo(CarbonImmutable::parse('2026-06-20 10:00:00'));

    $this->artisan('finance:generate-recurring-transactions')->assertSuccessful();

    Notification::assertSentToTimes($owner, RecurringRuleFailedNotification::class, 2);
});

it('alerts once per budget about what the run generated', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-15 10:00:00'));
    [$rule, $account, $currency] = dueRule(['category' => Category::Groceries, 'starts_on' => '2026-09-15']);
    RecurringRule::factory()->expense()->create([
        ...$rule->only(['account_id', 'amount', 'currency_id', 'decimal_places', 'frequency', 'interval', 'team_id', 'time_of_day', 'timezone', 'user_id']),
        'category' => Category::Groceries,
        'starts_on' => '2026-09-14',
    ]);
    $owner = memberOf($rule->team, Role::Owner);
    $budget = BudgetPeriod::factory()->forCategory(Category::Groceries)->running()->for($rule->team)->create([
        'amount' => '40',
        $currency->getForeignKey() => $currency->getKey(),
    ]);
    Notification::fake();

    $this->artisan('finance:generate-recurring-transactions')->assertSuccessful();

    Notification::assertSentToTimes($owner, BudgetAlertNotification::class, 1);
    Notification::assertSentTo($owner, BudgetAlertNotification::class, fn (BudgetAlertNotification $notification): bool => $notification->budget->is($budget)
        && $notification->kind === BudgetAlertKind::Over
        && $notification->percentage === 125.0);
});
