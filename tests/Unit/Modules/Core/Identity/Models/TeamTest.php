<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Models\Membership;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Illuminate\Support\Str;

afterEach(function (): void {
    Str::createRandomStringsNormally();
});

/**
 * Unit tests for \App\Modules\Core\Identity\Models\Team.
 */
it('generates a slug from the name when none is provided', function (): void {
    $team = Team::query()->create(['name' => 'Acme Corp']);

    expect($team->slug)->toBe(Str::slug('Acme Corp'));
});

it('suffixes a colliding slug with a random alphanumeric string', function (): void {
    Str::createRandomStringsUsing(fn (): string => 'a1b2c3');

    Team::query()->create(['name' => 'Acme']);
    $second = Team::query()->create(['name' => 'Acme']);

    expect($second->slug)->toBe('acme-a1b2c3');
});

it('uses the unsuffixed slug when a similarly prefixed slug exists', function (): void {
    Team::query()->create(['name' => 'Acme Extra']);

    $team = Team::query()->create(['name' => 'Acme']);

    expect($team->slug)->toBe('acme');
});

it('retries the random suffix when the candidate slug is already taken', function (): void {
    $calls = 0;
    Str::createRandomStringsUsing(function () use (&$calls): string {
        $calls++;

        return $calls === 1 ? 'taken1' : 'free12';
    });

    Team::query()->create(['name' => 'Acme']);
    Team::query()->create(['name' => 'Other', 'slug' => 'acme-taken1']);
    $second = Team::query()->create(['name' => 'Acme']);

    expect($second->slug)->toBe('acme-free12');
});

it('regenerates the slug when the name changes and excludes the current team', function (): void {
    Str::createRandomStringsUsing(fn (): string => 'x9y8z7');

    $team = Team::query()->create(['name' => 'Acme']);
    Team::query()->create(['name' => 'Globex']);

    $team->update(['name' => 'Globex']);

    expect($team->fresh()?->slug)->toBe('globex-x9y8z7');
});

it('exposes memberships through the users relation', function (): void {
    [$user, $team] = memberWithTeam();

    expect($team->users()->first()?->is($user))->toBeTrue()
        ->and($team->users()->first()?->membership)->toBeInstanceOf(Membership::class)
        ->and($team->users()->first()?->membership?->user)->toBeInstanceOf(User::class)
        ->and($team->users()->first()?->membership?->team)->toBeInstanceOf(Team::class);
});
