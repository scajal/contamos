<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Administration\Widgets\StatsOverviewWidget;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\App;
use Laravel\Paddle\Subscription;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Administration\Widgets\StatsOverviewWidget.
 */
it('renders the administration dashboard with its stats', function (): void {
    actingOperator();
    Team::factory()->count(2)->create();

    $this->get(route('filament.administration.pages.dashboard'))
        ->assertOk();

    Livewire::test(StatsOverviewWidget::class)
        ->assertOk()
        ->assertSee(trans('Teams'))
        ->assertSee(trans('Users'))
        ->assertSee(trans('Pro teams'))
        ->assertSee(trans('Revenue this month'))
        ->assertSee(trans('Transactions recorded'))
        ->assertSee(trans('Needs attention'));
});

it('counts pro teams by subscription and by granted plan', function (): void {
    actingOperator();
    subscribeToPro(Team::factory()->create());
    Team::factory()->grantedPlan(Plan::Pro)->create();
    Team::factory()->grantedPlan(Plan::Pro, CarbonImmutable::now()->subDay())->create();

    // A subscribed team overridden to Free is not on Pro.
    subscribeToPro(Team::factory()->grantedPlan(Plan::Free)->create());

    Livewire::test(StatsOverviewWidget::class)
        ->assertSee(trans(':count granted by hand', ['count' => 1]))
        ->assertSeeInOrder([trans('Pro teams'), '2']);
});

it('sums what was collected this month against last month', function (): void {
    actingOperator();
    $team = Team::factory()->create();
    $now = CarbonImmutable::now();

    $collected = fn (CarbonImmutable $billedAt, string $total, string $status = 'completed'): \App\Modules\Billing\Models\Transaction => $team->billingTransactions()->create([
        'billed_at' => $billedAt,
        'currency' => 'USD',
        'paddle_id' => 'txn_'.fake()->unique()->lexify('??????'),
        'status' => $status,
        'tax' => '0.00',
        'total' => $total,
    ]);

    // Totals are stored in minor units, the way Paddle writes them.
    $collected($now->startOfMonth()->addDay(), '499');
    $collected($now->startOfMonth()->addDays(2), '499', 'paid');
    $collected($now->startOfMonth()->addDays(3), '499', 'canceled');
    $collected($now->subMonth()->startOfMonth()->addDay(), '2999');

    App::setLocale('es');

    Livewire::test(StatsOverviewWidget::class)
        ->assertSee('USD 9,98')
        ->assertSee(trans(':amount last month', ['amount' => 'USD 29,99']));
});

it('counts the subscriptions that need attention', function (): void {
    actingOperator();
    $pastDue = Team::factory()->create();
    subscribeToPro($pastDue);
    $pastDue->subscriptions()->update(['status' => Subscription::STATUS_PAST_DUE]);

    $cancelling = Team::factory()->create();
    subscribeToPro($cancelling);
    $cancelling->subscriptions()->update(['status' => Subscription::STATUS_CANCELED, 'ends_at' => CarbonImmutable::now()->addWeek()]);

    Livewire::test(StatsOverviewWidget::class)
        ->assertSee(trans(':past_due past due, :cancelling cancelling', ['past_due' => 1, 'cancelling' => 1]));
});
