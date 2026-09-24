<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Finance\Notifications\Team\MonthlySummaryNotification;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Feature tests for \App\Modules\Core\Finance\Services\MonthlySummary\Commands\SendMonthlySummariesCommand.
 */
beforeEach(function (): void {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-09-01 09:00:00'));
});

/**
 * Create an active account on the team holding a currency
 * of two decimal places with the given code.
 *
 * @return array{0: \App\Modules\Core\Finance\Models\Team\Account, 1: \App\Modules\Core\Catalog\Models\Currency}
 */
function summaryAccount(Team $team, string $code = 'ARS', bool $isActive = true): array
{
    $currency = Currency::query()->where('code', $code)->first()
        ?? Currency::factory()->active()->fiat()->create(['code' => $code, 'decimal_places' => 2, 'symbol' => $code === 'ARS' ? '$' : 'US$']);
    $account = Account::factory()->for($team)->create(['is_active' => $isActive]);
    $account->currencies()->attach($currency, ['balance' => '0']);

    return [$account, $currency];
}

/**
 * Record a movement of the given type on the account.
 */
function summaryMovement(Account $account, Currency $currency, string $type, string $amount, Category $category, string $occurredAt = '2026-08-15 12:00:00'): Transaction
{
    return Transaction::factory()->{$type}()->for($account)->for($currency)->create([
        'amount' => $amount,
        'category' => $category,
        'occurred_at' => CarbonImmutable::parse($occurredAt),
    ]);
}

it('schedules the summaries at nine UTC on the first of the month', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains($event->command ?? '', 'finance:send-monthly-summaries'));

    expect($event)->not->toBeNull()
        ->and($event?->expression)->toBe('0 9 1 * *')
        ->and($event?->timezone)->toBe('UTC')
        ->and($event?->withoutOverlapping)->toBeTrue();
});

it('emails the owner and admins of a team with activity last month', function (): void {
    [$owner, $team] = memberWithTeam();
    subscribeToPro($team);
    $admin = memberOf($team, Role::Admin);
    $member = memberOf($team, Role::Member);
    $quiet = memberOf($team, Role::Admin);
    $team->users()->updateExistingPivot($quiet->getKey(), [
        'notification_preferences' => ['monthly_summary' => false],
    ]);
    [$account, $currency] = summaryAccount($team);
    summaryMovement($account, $currency, 'expense', '40', Category::Food);

    $this->artisan('finance:send-monthly-summaries')
        ->expectsOutput('Sent the summary of 2026-08 to 1 teams.')
        ->assertSuccessful();

    Notification::assertSentTo([$owner, $admin], MonthlySummaryNotification::class, fn (MonthlySummaryNotification $notification): bool => $notification->team->is($team)
        && $notification->month === '2026-08');
    Notification::assertNotSentTo([$member, $quiet], MonthlySummaryNotification::class);
});

it('sends nothing to a team with no activity last month', function (string $occurredAt): void {
    [$owner, $team] = memberWithTeam();
    [$account, $currency] = summaryAccount($team);
    summaryMovement($account, $currency, 'expense', '40', Category::Food, $occurredAt);

    $this->artisan('finance:send-monthly-summaries')->assertSuccessful();

    Notification::assertNothingSent();
})->with([
    'the month before' => ['2026-07-31 23:59:00'],
    'the current month' => ['2026-09-01 08:00:00'],
]);

it('sends nothing when last month only moved money on inactive accounts', function (): void {
    [$owner, $team] = memberWithTeam();
    [$account, $currency] = summaryAccount($team, isActive: false);
    summaryMovement($account, $currency, 'expense', '40', Category::Food);

    $this->artisan('finance:send-monthly-summaries')
        ->expectsOutput('Sent the summary of 2026-08 to 0 teams.')
        ->assertSuccessful();

    Notification::assertNothingSent();
});

it('lists the five categories that spent the most per currency', function (): void {
    [$owner, $team] = memberWithTeam();
    [$account, $peso] = summaryAccount($team);
    [$dollarAccount, $dollar] = summaryAccount($team, 'USD');

    foreach ([
        [Category::Food, '10'], [Category::Housing, '60'], [Category::Health, '30'],
        [Category::Transport, '50'], [Category::Utilities, '20'], [Category::Shopping, '40'],
    ] as [$category, $amount]) {
        summaryMovement($account, $peso, 'expense', $amount, $category);
    }

    summaryMovement($account, $peso, 'income', '500', Category::Salary);
    summaryMovement($dollarAccount, $dollar, 'expense', '5', Category::Food);
    summaryMovement($dollarAccount, $dollar, 'income', '100', Category::Salary, '2026-07-10 12:00:00');

    $this->artisan('finance:send-monthly-summaries')->assertSuccessful();

    Notification::assertSentTo($owner, MonthlySummaryNotification::class, function (MonthlySummaryNotification $notification): bool {
        [$pesos, $dollars] = $notification->currencies;

        return array_column($pesos['categories'], 'category') === ['housing', 'transport', 'shopping', 'health', 'utilities']
            && array_column($dollars['categories'], 'category') === ['food']
            && (float) $dollars['income'] === 0.0;
    });
});

it('carries the figures the monthly report shows for the same month', function (): void {
    [$owner, $team] = actingMember();
    subscribeToPro($team);
    [$account, $peso] = summaryAccount($team);
    [$dollarAccount, $dollar] = summaryAccount($team, 'USD');
    $tag = Tag::factory()->active()->for($team)->create(['name' => 'Trip']);
    $august = CarbonImmutable::parse('2026-08-01');
    BudgetPeriod::factory()->singleMonth($august)->forCategory(Category::Food)->for($team)->for($peso)->create(['amount' => '100']);
    BudgetPeriod::factory()->singleMonth($august)->forTag($tag)->for($team)->for($dollar)->create(['amount' => '50']);

    summaryMovement($account, $peso, 'income', '1000.50', Category::Salary);
    summaryMovement($account, $peso, 'expense', '120.25', Category::Food);
    summaryMovement($account, $peso, 'expense', '30', Category::Transport);
    summaryMovement($dollarAccount, $dollar, 'expense', '20', Category::Shopping)->attachTags([$tag->getKey()]);

    $this->artisan('finance:send-monthly-summaries')->assertSuccessful();

    /** @var \App\Modules\Core\Finance\Notifications\Team\MonthlySummaryNotification $notification */
    $notification = Notification::sent($owner, MonthlySummaryNotification::class)->sole();
    [$pesos, $dollars] = $notification->currencies;

    expect($pesos['budgets'])->toHaveCount(1)
        ->and($pesos['budgets'][0]['category'])->toBe('food')
        ->and((float) $pesos['budgets'][0]['remaining'])->toBe(-20.25)
        ->and($dollars['budgets'][0]['tag'])->toBe('Trip');

    $this->get(route('finance.teams.monthly.index', ['team' => $team->getRouteKey(), 'month' => '2026-08']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->loadDeferredProps(fn (Assert $page): Assert => $page
                ->where('figures.0.currency.code', $pesos['currency']['code'])
                ->where('figures.0.income.amount', $pesos['income'])
                ->where('figures.0.expenses.amount', $pesos['expenses'])
                ->where('figures.0.net.amount', $pesos['net'])
                ->where('figures.0.categories.0.category', $pesos['categories'][0]['category'])
                ->where('figures.0.categories.0.expenses', $pesos['categories'][0]['expenses'])
                ->where('figures.0.categories.0.budget.amount', $pesos['budgets'][0]['amount'])
                ->where('figures.0.categories.0.budget.spent', $pesos['budgets'][0]['spent'])
                ->where('figures.0.categories.0.budget.remaining', $pesos['budgets'][0]['remaining'])
                ->where('figures.0.categories.1.expenses', $pesos['categories'][1]['expenses'])
                ->where('figures.1.currency.code', $dollars['currency']['code'])
                ->where('figures.1.expenses.amount', $dollars['expenses'])
                ->where('figures.1.tags.0.budget.amount', $dollars['budgets'][0]['amount'])
                ->where('figures.1.tags.0.budget.spent', $dollars['budgets'][0]['spent'])));
});
