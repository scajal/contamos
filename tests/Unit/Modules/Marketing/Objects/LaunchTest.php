<?php

declare(strict_types=1);

use App\Modules\Marketing\Enums\Launch\Phase;
use App\Modules\Marketing\Objects\Launch;
use Carbon\CarbonImmutable;

/**
 * Unit tests for \App\Modules\Marketing\Objects\Launch.
 */
it('places a moment in the phase of the launch it falls in', function (string $moment, Phase $phase): void {
    expect(Launch::at(CarbonImmutable::parse($moment))?->phase)->toBe($phase);
})->with([
    'weeks before' => ['2026-09-27 12:00:00 UTC', Phase::Upcoming],
    'the minute before it goes live' => ['2026-10-24 00:00:59 America/Los_Angeles', Phase::Upcoming],
    'the minute it goes live' => ['2026-10-24 00:01:00 America/Los_Angeles', Phase::Live],
    'the last minute of launch day' => ['2026-10-24 23:59:59 America/Los_Angeles', Phase::Live],
    'the day after' => ['2026-10-25 00:00:00 America/Los_Angeles', Phase::Offer],
    'the code\'s last minute' => ['2026-10-31 23:58:59 UTC', Phase::Offer],
]);

it('announces nothing once the code has expired', function (string $moment): void {
    expect(Launch::at(CarbonImmutable::parse($moment)))->toBeNull();
})->with([
    'the minute it expires' => ['2026-10-31 23:59:00 UTC'],
    'weeks later' => ['2026-12-01 12:00:00 UTC'],
]);

it('keeps the code back until the launch is live', function (Phase $phase, ?string $code): void {
    expect(new Launch($phase)->toArray())->toBe([
        'phase' => $phase->value,
        'url' => Launch::URL,
        'code' => $code,
    ]);
})->with([
    'upcoming' => [Phase::Upcoming, null],
    'live' => [Phase::Live, 'PRODUCTHUNT'],
    'offer' => [Phase::Offer, 'PRODUCTHUNT'],
]);
