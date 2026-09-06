<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\Team\TeamInvitation;
use App\Modules\Core\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

afterEach(function (): void {
    Str::createRandomStringsNormally();
});

/**
 * Unit tests for \App\Modules\Core\Identity\Models\Team\TeamInvitation.
 */
it('generates a code and an expiration date when none are provided', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-06 12:00:00'));
    Str::createRandomStringsUsing(fn (int $length): string => str_repeat('c', $length));

    $invitation = TeamInvitation::factory()->create(['code' => null, 'expires_at' => null]);

    expect($invitation->code)->toBe(str_repeat('c', 64))
        ->and($invitation->expires_at?->toDateTimeString())->toBe('2026-09-13 12:00:00');
});

it('keeps the provided code and expiration date', function (): void {
    $expiresAt = CarbonImmutable::parse('2026-10-01 00:00:00');

    $invitation = TeamInvitation::factory()->create(['code' => str_repeat('k', 64), 'expires_at' => $expiresAt]);

    expect($invitation->code)->toBe(str_repeat('k', 64))
        ->and($invitation->expires_at?->equalTo($expiresAt))->toBeTrue();
});

it('stores the email in lowercase', function (): void {
    $invitation = TeamInvitation::factory()->create(['email' => 'Ada@Example.com']);

    expect($invitation->email)->toBe('ada@example.com')
        ->and($invitation->fresh()?->email)->toBe('ada@example.com');
});

it('uses the code as the route key', function (): void {
    $invitation = TeamInvitation::factory()->create();

    expect($invitation->getRouteKeyName())->toBe('code')
        ->and($invitation->getRouteKey())->toBe($invitation->code);
});

it('exposes the team and the inviter', function (): void {
    $team = Team::factory()->create();
    $inviter = User::factory()->create();
    $invitation = TeamInvitation::factory()->for($team)->for($inviter, 'inviter')->create();

    expect($invitation->team->is($team))->toBeTrue()
        ->and($invitation->inviter->is($inviter))->toBeTrue()
        ->and($invitation->inviter()->getForeignKeyName())->toBe('invited_by');
});

it('derives the accepted, expired and pending states from its dates', function (): void {
    $pending = TeamInvitation::factory()->create();
    $accepted = TeamInvitation::factory()->accepted()->create();
    $expired = TeamInvitation::factory()->expired()->create();

    expect($pending->isPending())->toBeTrue()
        ->and($pending->isAccepted())->toBeFalse()
        ->and($pending->isExpired())->toBeFalse()
        ->and($accepted->isPending())->toBeFalse()
        ->and($accepted->isAccepted())->toBeTrue()
        ->and($expired->isPending())->toBeFalse()
        ->and($expired->isExpired())->toBeTrue();
});

it('matches the invited email ignoring case', function (): void {
    $invitation = TeamInvitation::factory()->create(['email' => 'ada@example.com']);

    expect($invitation->isFor('Ada@Example.com'))->toBeTrue()
        ->and($invitation->isFor('grace@example.com'))->toBeFalse();
});

it('marks the invitation as accepted', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-06 12:00:00'));
    $invitation = TeamInvitation::factory()->create();

    $invitation->accept();

    expect($invitation->fresh()?->accepted_at?->toDateTimeString())->toBe('2026-09-06 12:00:00');
});

it('renews the code and the expiration date', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-06 12:00:00'));
    $invitation = TeamInvitation::factory()->expired()->create(['code' => str_repeat('o', 64)]);
    Str::createRandomStringsUsing(fn (int $length): string => str_repeat('n', $length));

    $invitation->renew();

    expect($invitation->fresh()?->code)->toBe(str_repeat('n', 64))
        ->and($invitation->fresh()?->expires_at?->toDateTimeString())->toBe('2026-09-13 12:00:00')
        ->and($invitation->isPending())->toBeTrue();
});

it('scopes pending invitations', function (): void {
    $pending = TeamInvitation::factory()->create();
    TeamInvitation::factory()->accepted()->create();
    TeamInvitation::factory()->expired()->create();

    expect(TeamInvitation::query()->pending()->pluck($pending->getKeyName())->all())->toBe([$pending->getKey()]);
});

it('scopes invitations by their derived status', function (): void {
    $pending = TeamInvitation::factory()->create();
    $accepted = TeamInvitation::factory()->accepted()->create();
    $expired = TeamInvitation::factory()->expired()->create();

    expect(TeamInvitation::query()->status('pending')->pluck($pending->getKeyName())->all())->toBe([$pending->getKey()])
        ->and(TeamInvitation::query()->status('accepted')->pluck($accepted->getKeyName())->all())->toBe([$accepted->getKey()])
        ->and(TeamInvitation::query()->status('expired')->pluck($expired->getKeyName())->all())->toBe([$expired->getKey()])
        ->and(TeamInvitation::query()->status('unknown')->count())->toBe(3);
});
