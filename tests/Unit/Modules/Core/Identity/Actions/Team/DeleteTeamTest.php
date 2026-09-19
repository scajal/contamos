<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Identity\Actions\Team\DeleteTeam;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\Team\TeamInvitation;
use App\Modules\Core\Identity\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Paddle\Exceptions\PaddleException;

/**
 * Unit tests for \App\Modules\Core\Identity\Actions\Team\DeleteTeam.
 */
it('throws when no user was provided', function (): void {
    $team = Team::factory()->create();

    expect(fn (): ?Team => (new DeleteTeam)->using($team))
        ->toThrow(Exception::class, 'A team cannot be deleted without a user.');
});

it('deletes the team with what it owns', function (): void {
    [$user, $team] = memberWithTeam();
    Account::factory()->for($team)->create();
    TeamInvitation::factory()->for($team)->create();
    $team->customer()->create(['paddle_id' => 'ctm_test', 'name' => $team->name, 'email' => $user->email]);

    (new DeleteTeam)
        ->by($user)
        ->using($team);

    expect(Team::query()->whereKey($team->getKey())->exists())->toBeFalse()
        ->and(Account::query()->where('team_id', $team->getKey())->exists())->toBeFalse()
        ->and(TeamInvitation::query()->where('team_id', $team->getKey())->exists())->toBeFalse()
        ->and($user->fresh()?->teams()->count())->toBe(0)
        ->and(\App\Modules\Billing\Models\Customer::query()->where('billable_id', $team->getKey())->exists())->toBeFalse();
});

it('moves the members on the team to another of their teams', function (): void {
    [$user, $team] = memberWithTeam();
    $other = Team::factory()->create();
    $user->teams()->attach($other, ['role' => Role::Owner]);
    $member = User::factory()->create();
    memberOf($team, Role::Admin, $member);
    $member->switchTeam($team);

    $next = (new DeleteTeam)
        ->by($user)
        ->using($team);

    expect($next?->getKey())->toBe($other->getKey())
        ->and($user->fresh()?->current_team_id)->toBe($other->getKey())
        ->and($member->fresh()?->current_team_id)->toBeNull();
});

it('keeps the current team of members who were on another team', function (): void {
    [$user, $team] = memberWithTeam();
    $other = Team::factory()->create();
    $user->teams()->attach($other, ['role' => Role::Owner]);
    $user->switchTeam($other);

    $next = (new DeleteTeam)
        ->by($user)
        ->using($team);

    expect($next?->getKey())->toBe($other->getKey())
        ->and($user->fresh()?->current_team_id)->toBe($other->getKey());
});

it('clears the current team when the user has no team left', function (): void {
    [$user, $team] = memberWithTeam();

    $next = (new DeleteTeam)
        ->by($user)
        ->using($team);

    expect($next)->toBeNull()
        ->and($user->fresh()?->current_team_id)->toBeNull();
});

it('cancels a live subscription on paddle before deleting', function (): void {
    [$user, $team] = memberWithTeam();
    subscribeToPro($team);
    Http::fake([
        '*/subscriptions/*/cancel' => Http::response(['data' => [
            'status' => \Laravel\Paddle\Subscription::STATUS_CANCELED,
            'canceled_at' => '2026-09-09T12:00:00Z',
        ]]),
    ]);

    (new DeleteTeam)
        ->by($user)
        ->using($team);

    expect(Team::query()->whereKey($team->getKey())->exists())->toBeFalse()
        ->and(\App\Modules\Billing\Models\Subscription::query()->where('billable_id', $team->getKey())->exists())->toBeFalse();

    Http::assertSentCount(1);
});

it('does not delete the team when paddle refuses the cancellation', function (): void {
    [$user, $team] = memberWithTeam();
    subscribeToPro($team);
    Http::fake([
        '*/subscriptions/*/cancel' => Http::response(['error' => ['detail' => 'subscription is locked']], 400),
    ]);

    expect(fn (): ?Team => (new DeleteTeam)->by($user)->using($team))
        ->toThrow(PaddleException::class);

    expect(Team::query()->whereKey($team->getKey())->exists())->toBeTrue()
        ->and($user->fresh()?->current_team_id)->toBe($team->getKey());
});
