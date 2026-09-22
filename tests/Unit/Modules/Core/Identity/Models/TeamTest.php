<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Membership;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature as Pennant;

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

it('bills the owner of the team even when another member is acting', function (): void {
    [$owner, $team] = memberWithTeam();
    $admin = memberOf($team, Role::Admin);
    $this->actingAs($admin);

    expect($team->paddleEmail())->toBe($owner->email)
        ->and($team->paddleEmail())->not->toBe($admin->email)
        ->and($team->paddleName())->toBe($owner->name)
        ->and($team->paddleName())->not->toBe($team->name);
});

it('has no paddle email or name for a team without an owner', function (): void {
    $team = Team::factory()->create();

    expect($team->paddleEmail())->toBeNull()
        ->and($team->paddleName())->toBeNull();
});

it('lists the roles the team may hand out', function (): void {
    [$owner, $team] = memberWithTeam();

    expect($team->assignsRoles())->toBeFalse()
        ->and($team->assignableRoles())->toBe([Role::Admin]);

    subscribeToPro($team);
    Pennant::flushCache();

    expect($team->assignsRoles())->toBeTrue()
        ->and($team->assignableRoles())->toBe(Role::assignable());
});

it('bends a stored role to the role it acts as under the plan', function (): void {
    [$owner, $team] = memberWithTeam();

    expect($team->effectiveRole(Role::Member))->toBe(Role::Admin)
        ->and($team->effectiveRole(Role::Owner))->toBe(Role::Owner)
        ->and($team->effectiveRole(null))->toBeNull();

    subscribeToPro($team);
    Pennant::flushCache();

    expect($team->effectiveRole(Role::Member))->toBe(Role::Member);
});

it('exposes the user who owns the team', function (): void {
    [$owner, $team] = memberWithTeam();
    memberOf($team, Role::Admin);

    expect($team->owner?->is($owner))->toBeTrue();
});

it('reads the owner from the loaded members when the relation is loaded', function (): void {
    [$owner, $team] = memberWithTeam();
    $team->load('users');

    expect($team->owner?->is($owner))->toBeTrue();
});

it('has no owner when nobody belongs to the team', function (): void {
    $team = Team::factory()->create();

    expect($team->owner)->toBeNull();
});
