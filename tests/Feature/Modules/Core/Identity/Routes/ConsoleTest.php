<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team\Activity;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Spatie\Activitylog\Commands\CleanActivitylogCommand;

/**
 * Feature tests for the schedule registered in
 * app/Modules/Core/Identity/Routes/console.php.
 */
it('schedules the activity log clean command daily without overlapping', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains($event->command ?? '', 'activitylog:clean'));

    expect($event)->not->toBeNull()
        ->and($event?->expression)->toBe('0 3 * * *')
        ->and($event?->withoutOverlapping)->toBeTrue();
});

it('prunes the entries older than a year and keeps the younger ones', function (): void {
    [$user, $team] = actingMember();
    clearActivities();
    Tag::factory()->for($team)->create();

    $old = activitiesOf($team)->sole();
    $old->forceFill(['created_at' => CarbonImmutable::now()->subDays(366)])->save();

    Tag::factory()->for($team)->create();
    $young = activitiesOf($team)->last();

    $this->artisan(CleanActivitylogCommand::class)->assertSuccessful();

    expect(Activity::query()->whereKey($old->getKey())->exists())->toBeFalse()
        ->and(Activity::query()->whereKey($young?->getKey())->exists())->toBeTrue();
});
