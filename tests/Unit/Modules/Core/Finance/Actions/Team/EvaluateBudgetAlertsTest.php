<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Actions\Team\EvaluateBudgetAlerts;
use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetAlertKind;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\BudgetAlert;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Finance\Notifications\Team\BudgetAlertNotification;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

/**
 * Unit tests for \App\Modules\Core\Finance\Actions\Team\EvaluateBudgetAlerts.
 */
beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-15 12:00:00'));
});

/**
 * A Pro \App\Modules\Core\Identity\Models\Team with an Owner, an
 * Admin and a Member, and an active account in one currency.
 *
 * @return array{team: \App\Modules\Core\Identity\Models\Team, owner: \App\Modules\Core\Identity\Models\User, admin: \App\Modules\Core\Identity\Models\User, member: \App\Modules\Core\Identity\Models\User, account: \App\Modules\Core\Finance\Models\Team\Account, currency: \App\Modules\Core\Catalog\Models\Currency}
 */
function alertingTeam(): array
{
    [$owner, $team] = memberWithTeam();
    subscribeToPro($team);

    $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
    $account = Account::factory()->bank()->active()->for($team)->create();
    $account->currencies()->attach($currency);

    return [
        'team' => $team,
        'owner' => $owner,
        'admin' => memberOf($team, Role::Admin),
        'member' => memberOf($team, Role::Member),
        'account' => $account,
        'currency' => $currency,
    ];
}

/**
 * Record an expense on Food in the account, dated now unless
 * told otherwise.
 */
function spendOnFood(Account $account, Currency $currency, string $amount, ?CarbonImmutable $at = null): Transaction
{
    return Transaction::factory()->expense()->create([
        $account->getForeignKey() => $account->getKey(),
        'amount' => $amount,
        'category' => Category::Food,
        $currency->getForeignKey() => $currency->getKey(),
        'occurred_at' => $at ?? CarbonImmutable::now(),
    ]);
}

/**
 * A running Food budget of 100 in the currency, warning at
 * the given share.
 */
function foodBudget(Team $team, Currency $currency, ?int $threshold = 80): BudgetPeriod
{
    return BudgetPeriod::factory()->forCategory(Category::Food)->running()->for($team)->create([
        'amount' => '100',
        $currency->getForeignKey() => $currency->getKey(),
        'warning_threshold' => $threshold,
    ]);
}

/**
 * Evaluate the team's budgets after a movement dated now.
 */
function evaluateAlerts(Team $team, ?CarbonImmutable $at = null): void
{
    new EvaluateBudgetAlerts()->for($team)->using([$at ?? CarbonImmutable::now()]);
}

it('throws when a team was not provided', function (): void {
    new EvaluateBudgetAlerts()->using([CarbonImmutable::now()]);
})->throws(Exception::class, 'Budget alerts cannot be evaluated without a team.');

it('warns the Owner and Admins, not Members, once the warning threshold is reached', function (): void {
    ['team' => $team, 'owner' => $owner, 'admin' => $admin, 'member' => $member, 'account' => $account, 'currency' => $currency] = alertingTeam();
    $budget = foodBudget($team, $currency);
    spendOnFood($account, $currency, '80');
    Notification::fake();

    evaluateAlerts($team);

    Notification::assertSentTo(
        [$owner, $admin],
        BudgetAlertNotification::class,
        fn (BudgetAlertNotification $notification): bool => $notification->budget->is($budget)
            && $notification->kind === BudgetAlertKind::Warning
            && $notification->month->toDateString() === '2026-09-01'
            && bccomp($notification->amount, '100', 2) === 0
            && bccomp($notification->spent, '80', 2) === 0
            && $notification->percentage === 80.0,
    );
    Notification::assertNotSentTo($member, BudgetAlertNotification::class);
});

it('stays quiet below the warning threshold', function (): void {
    ['team' => $team, 'account' => $account, 'currency' => $currency] = alertingTeam();
    foodBudget($team, $currency);
    spendOnFood($account, $currency, '79.99');
    Notification::fake();

    evaluateAlerts($team);

    Notification::assertNothingSent();
    expect(BudgetAlert::query()->count())->toBe(0);
});

it('sends the over-budget alert past the whole amount, with or without a warning threshold', function (?int $threshold): void {
    ['team' => $team, 'owner' => $owner, 'account' => $account, 'currency' => $currency] = alertingTeam();
    foodBudget($team, $currency, $threshold);
    spendOnFood($account, $currency, '100.01');
    Notification::fake();

    evaluateAlerts($team);

    Notification::assertSentToTimes($owner, BudgetAlertNotification::class, 1);
    Notification::assertSentTo($owner, BudgetAlertNotification::class, fn (BudgetAlertNotification $notification): bool => $notification->kind === BudgetAlertKind::Over);
})->with([
    'with a warning threshold' => [80],
    'without one' => [null],
]);

it('does not call spending exactly the amount over', function (): void {
    ['team' => $team, 'owner' => $owner, 'account' => $account, 'currency' => $currency] = alertingTeam();
    foodBudget($team, $currency, null);
    spendOnFood($account, $currency, '100');
    Notification::fake();

    evaluateAlerts($team);

    Notification::assertNothingSent();
});

it('holds the warning as sent when a movement jumps straight past the amount', function (): void {
    ['team' => $team, 'owner' => $owner, 'account' => $account, 'currency' => $currency] = alertingTeam();
    $budget = foodBudget($team, $currency);
    spendOnFood($account, $currency, '120');
    Notification::fake();

    evaluateAlerts($team);
    evaluateAlerts($team);

    Notification::assertSentToTimes($owner, BudgetAlertNotification::class, 1);
    expect($budget->alerts()->orderBy('kind')->get()->pluck('kind')->all())
        ->toBe([BudgetAlertKind::Over, BudgetAlertKind::Warning]);
});

it('warns first and alerts again once the month goes over', function (): void {
    ['team' => $team, 'owner' => $owner, 'account' => $account, 'currency' => $currency] = alertingTeam();
    foodBudget($team, $currency);
    Notification::fake();

    spendOnFood($account, $currency, '85');
    evaluateAlerts($team);
    spendOnFood($account, $currency, '20');
    evaluateAlerts($team);

    Notification::assertSentToTimes($owner, BudgetAlertNotification::class, 2);
    Notification::assertSentTo($owner, BudgetAlertNotification::class, fn (BudgetAlertNotification $notification): bool => $notification->kind === BudgetAlertKind::Over
        && $notification->percentage === 105.0);
});

it('sends nothing for a second qualifying movement in the same month', function (): void {
    ['team' => $team, 'owner' => $owner, 'account' => $account, 'currency' => $currency] = alertingTeam();
    foodBudget($team, $currency);
    Notification::fake();

    spendOnFood($account, $currency, '85');
    evaluateAlerts($team);
    spendOnFood($account, $currency, '5');
    evaluateAlerts($team);

    Notification::assertSentToTimes($owner, BudgetAlertNotification::class, 1);
});

it('re-arms both thresholds when a new month starts', function (): void {
    ['team' => $team, 'owner' => $owner, 'account' => $account, 'currency' => $currency] = alertingTeam();
    $budget = foodBudget($team, $currency);
    BudgetAlert::factory()->for($budget)->create(['month' => CarbonImmutable::parse('2026-09-01')]);
    BudgetAlert::factory()->over()->for($budget)->create(['month' => CarbonImmutable::parse('2026-09-01')]);
    $this->travelTo(CarbonImmutable::parse('2026-10-03 09:00:00'));
    spendOnFood($account, $currency, '110');
    Notification::fake();

    evaluateAlerts($team);

    Notification::assertSentTo($owner, BudgetAlertNotification::class, fn (BudgetAlertNotification $notification): bool => $notification->kind === BudgetAlertKind::Over
        && $notification->month->toDateString() === '2026-10-01');
});

it('never alerts about a budget that is not running', function (string $state): void {
    ['team' => $team, 'account' => $account, 'currency' => $currency] = alertingTeam();
    BudgetPeriod::factory()->forCategory(Category::Food)->{$state}()->for($team)->create([
        'amount' => '100',
        $currency->getForeignKey() => $currency->getKey(),
        'warning_threshold' => 50,
    ]);
    spendOnFood($account, $currency, '500');
    Notification::fake();

    evaluateAlerts($team);

    Notification::assertNothingSent();
})->with(['paused', 'scheduled', 'ended']);

it('leaves the budgets alone when no movement falls in the current month', function (): void {
    ['team' => $team, 'account' => $account, 'currency' => $currency] = alertingTeam();
    foodBudget($team, $currency);
    spendOnFood($account, $currency, '500');
    Notification::fake();

    new EvaluateBudgetAlerts()->for($team)->using([CarbonImmutable::parse('2026-08-31 23:00:00'), null]);

    Notification::assertNothingSent();
});

it('never alerts on a plan without budgets', function (): void {
    [$owner, $team] = memberWithTeam();
    $currency = Currency::factory()->active()->fiat()->create();
    $account = Account::factory()->bank()->active()->for($team)->create();
    $account->currencies()->attach($currency);
    foodBudget($team, $currency);
    spendOnFood($account, $currency, '500');
    Notification::fake();

    evaluateAlerts($team);

    Notification::assertNothingSent();
});

it('alerts about a tag budget from the movements carrying the tag', function (): void {
    ['team' => $team, 'owner' => $owner, 'account' => $account, 'currency' => $currency] = alertingTeam();
    $tag = Tag::factory()->for($team)->create();
    $budget = BudgetPeriod::factory()->forTag($tag)->running()->create([
        'amount' => '50',
        $currency->getForeignKey() => $currency->getKey(),
    ]);
    spendOnFood($account, $currency, '60')->attachTags([$tag->getKey()]);
    Notification::fake();

    evaluateAlerts($team);

    Notification::assertSentTo($owner, BudgetAlertNotification::class, fn (BudgetAlertNotification $notification): bool => $notification->budget->is($budget)
        && $notification->percentage === 120.0);
});

it('spares a recipient who switched budget alerts off', function (): void {
    ['team' => $team, 'owner' => $owner, 'admin' => $admin, 'account' => $account, 'currency' => $currency] = alertingTeam();
    $team->users()->updateExistingPivot($admin->getKey(), [
        'notification_preferences' => ['budget_alerts' => false],
    ]);
    foodBudget($team, $currency);
    spendOnFood($account, $currency, '90');
    Notification::fake();

    evaluateAlerts($team);

    Notification::assertSentTo($owner, BudgetAlertNotification::class);
    Notification::assertNotSentTo($admin, BudgetAlertNotification::class);
});
