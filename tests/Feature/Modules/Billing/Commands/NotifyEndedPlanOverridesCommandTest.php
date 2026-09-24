<?php

declare(strict_types=1);

use App\Modules\Billing\Commands\NotifyEndedPlanOverridesCommand;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Notifications\Team\ProEndedNotification;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\artisan;

beforeEach(function (): void {
    Notification::fake();
});

/**
 * Feature tests for \App\Modules\Billing\Commands\NotifyEndedPlanOverridesCommand
 * and its schedule in app/Modules/Billing/Routes/console.php.
 */
it('is scheduled hourly without overlapping', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains($event->command ?? '', 'billing:notify-ended-plan-overrides'));

    expect($event)->not->toBeNull()
        ->and($event?->expression)->toBe('0 * * * *')
        ->and($event?->withoutOverlapping)->toBeTrue();
});

it('emails the owner once the pro plan granted by hand has expired', function (): void {
    [$owner, $team] = memberWithTeam();
    $admin = memberOf($team);
    $team->update(['plan_override' => Plan::Pro, 'plan_override_expires_at' => CarbonImmutable::now()->subMinute()]);

    artisan(NotifyEndedPlanOverridesCommand::class)
        ->expectsOutput('Notified 1 team owners.')
        ->assertSuccessful();

    Notification::assertSentTo($owner, ProEndedNotification::class, fn (ProEndedNotification $notification): bool => $notification->team->is($team));
    Notification::assertNotSentTo($admin, ProEndedNotification::class);
});

it('emails the owner once however many times it runs', function (): void {
    [$owner, $team] = memberWithTeam();
    $team->update(['plan_override' => Plan::Pro, 'plan_override_expires_at' => CarbonImmutable::now()->subMinute()]);

    artisan(NotifyEndedPlanOverridesCommand::class)->assertSuccessful();
    artisan(NotifyEndedPlanOverridesCommand::class)
        ->expectsOutput('Notified 0 team owners.')
        ->assertSuccessful();

    Notification::assertSentToTimes($owner, ProEndedNotification::class, 1);
});

it('emails the owner again when a later grant expires too', function (): void {
    [$owner, $team] = memberWithTeam();
    $team->update(['plan_override' => Plan::Pro, 'plan_override_expires_at' => CarbonImmutable::now()->subDay()]);
    artisan(NotifyEndedPlanOverridesCommand::class)->assertSuccessful();

    $team->refresh()->update(['plan_override_expires_at' => CarbonImmutable::now()->addDay()]);
    $this->travel(2)->days();
    artisan(NotifyEndedPlanOverridesCommand::class)->assertSuccessful();

    Notification::assertSentToTimes($owner, ProEndedNotification::class, 2);
});

it('leaves alone the grants that did not end a pro plan', function (?Plan $plan, ?CarbonImmutable $expiresAt): void {
    [, $team] = memberWithTeam();
    $team->update(['plan_override' => $plan, 'plan_override_expires_at' => $expiresAt]);

    artisan(NotifyEndedPlanOverridesCommand::class)->assertSuccessful();

    expect($team->fresh()?->plan_override_end_handled_at)->toBeNull();
    Notification::assertNothingSent();
})->with([
    'unexpired pro' => fn (): array => [Plan::Pro, CarbonImmutable::now()->addMinute()],
    'pro without expiry' => fn (): array => [Plan::Pro, null],
    'expired free' => fn (): array => [Plan::Free, CarbonImmutable::now()->subMinute()],
    'no grant' => fn (): array => [null, null],
]);

it('handles the end without emailing while a paddle subscription keeps the team on pro', function (): void {
    [, $team] = memberWithTeam();
    subscribeToPro($team);
    $team->update(['plan_override' => Plan::Pro, 'plan_override_expires_at' => CarbonImmutable::now()->subMinute()]);

    artisan(NotifyEndedPlanOverridesCommand::class)->assertSuccessful();

    expect($team->fresh()?->plan_override_end_handled_at)->toBeInstanceOf(CarbonImmutable::class);
    Notification::assertNothingSent();
});

it('emails the owner when the grant outlived a subscription that already ended', function (): void {
    [$owner, $team] = memberWithTeam();
    subscribeToPro($team);
    $team->update(['plan_override' => Plan::Pro, 'plan_override_expires_at' => CarbonImmutable::now()->addDay()]);
    cancelPro($team);
    $this->travel(2)->days();

    artisan(NotifyEndedPlanOverridesCommand::class)->assertSuccessful();

    Notification::assertSentToTimes($owner, ProEndedNotification::class, 1);
});

it('handles the end of a team without an owner without emailing anybody', function (): void {
    $team = Team::factory()->grantedPlan(Plan::Pro, CarbonImmutable::now()->subMinute())->create();

    artisan(NotifyEndedPlanOverridesCommand::class)
        ->expectsOutput('Notified 0 team owners.')
        ->assertSuccessful();

    expect($team->fresh()?->plan_override_end_handled_at)->toBeInstanceOf(CarbonImmutable::class);
    Notification::assertNothingSent();
});
