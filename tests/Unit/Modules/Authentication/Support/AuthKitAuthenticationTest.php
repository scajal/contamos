<?php

declare(strict_types=1);

use App\Modules\Authentication\Support\AuthKitAuthentication;
use App\Modules\Core\Identity\Models\User;
use Laravel\WorkOS\User as WorkOSUser;

/**
 * Unit tests for \App\Modules\Authentication\Support\AuthKitAuthentication.
 */
it('creates a user from the WorkOS profile', function (): void {
    $this->travelTo('2024-06-01 12:00:00');

    $workosUser = new WorkOSUser(
        id: 'user_123',
        organizationId: null,
        firstName: 'Ada',
        lastName: 'Lovelace',
        email: 'ada@example.com',
        avatar: 'https://example.com/ada.png',
    );

    $user = (new AuthKitAuthentication)->createUsing($workosUser);

    expect($user->external_id)->toBe('user_123')
        ->and($user->email)->toBe('ada@example.com')
        ->and($user->name)->toBe('Ada Lovelace')
        ->and($user->avatar)->toBe('https://example.com/ada.png')
        ->and($user->is_active)->toBeTrue()
        ->and($user->email_verified_at?->equalTo(now()))->toBeTrue();
});

it('creates a user with an empty avatar when WorkOS omits it', function (): void {
    $workosUser = new WorkOSUser(
        id: 'user_456',
        organizationId: null,
        firstName: 'Grace',
        lastName: 'Hopper',
        email: 'grace@example.com',
    );

    $user = (new AuthKitAuthentication)->createUsing($workosUser);

    expect($user->avatar)->toBe('');
});

it('finds a user by the WorkOS identifier', function (): void {
    $existing = User::factory()->create(['external_id' => 'user_123']);
    $workosUser = new WorkOSUser(
        id: 'user_123',
        organizationId: null,
        firstName: 'Ada',
        lastName: 'Lovelace',
        email: 'ada@example.com',
    );

    expect((new AuthKitAuthentication)->findUsing($workosUser)?->is($existing))->toBeTrue();
});

it('returns null when no user matches the WorkOS identifier', function (): void {
    $workosUser = new WorkOSUser(
        id: 'user_missing',
        organizationId: null,
        firstName: 'Ada',
        lastName: 'Lovelace',
        email: 'ada@example.com',
    );

    expect((new AuthKitAuthentication)->findUsing($workosUser))->toBeNull();
});

it('finds a user whose WorkOS identifier changed by email', function (): void {
    $existing = User::factory()->create(['email' => 'ada@example.com', 'external_id' => 'user_old']);
    $workosUser = new WorkOSUser(
        id: 'user_new',
        organizationId: null,
        firstName: 'Ada',
        lastName: 'Lovelace',
        email: 'ada@example.com',
    );

    expect((new AuthKitAuthentication)->findUsing($workosUser)?->is($existing))->toBeTrue();
});

it('prefers the user matching the WorkOS identifier over one matching the email', function (): void {
    User::factory()->create(['email' => 'ada@example.com', 'external_id' => 'user_other']);
    $matching = User::factory()->create(['email' => 'ada.lovelace@example.com', 'external_id' => 'user_123']);
    $workosUser = new WorkOSUser(
        id: 'user_123',
        organizationId: null,
        firstName: 'Ada',
        lastName: 'Lovelace',
        email: 'ada@example.com',
    );

    expect((new AuthKitAuthentication)->findUsing($workosUser)?->is($matching))->toBeTrue();
});

it('records the WorkOS identifier the user signed in as', function (): void {
    $user = User::factory()->create(['email' => 'ada@example.com', 'external_id' => 'user_old']);
    $workosUser = new WorkOSUser(
        id: 'user_new',
        organizationId: null,
        firstName: 'Ada',
        lastName: 'Lovelace',
        email: 'ada@example.com',
    );

    (new AuthKitAuthentication)->updateUsing($user, $workosUser);

    expect($user->refresh()->external_id)->toBe('user_new');
});
