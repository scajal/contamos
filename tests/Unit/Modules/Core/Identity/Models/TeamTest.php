<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Models\Membership;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Illuminate\Support\Str;

/**
 * Unit tests for \App\Modules\Core\Identity\Models\Team.
 */
it('generates a slug from the name when none is provided', function (): void {
    $team = Team::query()->create(['name' => 'Acme Corp']);

    expect($team->slug)->toBe(Str::slug('Acme Corp'));
});

it('suffixes a colliding slug with an incrementing number', function (): void {
    Team::query()->create(['name' => 'Acme']);
    $second = Team::query()->create(['name' => 'Acme']);

    expect($second->slug)->toBe('acme-1');
});

it('ignores similarly prefixed slugs that are not numeric suffixes', function (): void {
    Team::query()->create(['name' => 'Acme']);
    Team::query()->create(['name' => 'Acme Extra']);
    $third = Team::query()->create(['name' => 'Acme']);

    expect($third->slug)->toBe('acme-1');
});

it('regenerates the slug when the name changes and excludes the current team', function (): void {
    $team = Team::query()->create(['name' => 'Acme']);
    Team::query()->create(['name' => 'Globex']);

    $team->update(['name' => 'Globex']);

    expect($team->fresh()?->slug)->toBe('globex-1');
});

it('exposes memberships through the users relation', function (): void {
    [$user, $team] = memberWithTeam();

    expect($team->users()->first()?->is($user))->toBeTrue()
        ->and($team->users()->first()?->membership)->toBeInstanceOf(Membership::class)
        ->and($team->users()->first()?->membership?->user)->toBeInstanceOf(User::class)
        ->and($team->users()->first()?->membership?->team)->toBeInstanceOf(Team::class);
});
