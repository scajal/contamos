<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Identity\Models\User;
use Database\Seeders\StressSeeder;

/**
 * Feature tests for \Database\Seeders\StressSeeder.
 */
it('throws when no user exists', function (): void {
    (new StressSeeder(transactionCount: 1))->run();
})->throws(\RuntimeException::class, 'Stress seeding requires an existing user. Log in once, then run this seeder.');

it('throws when the given email does not match a user', function (): void {
    (new StressSeeder(email: 'missing@example.com', transactionCount: 1))->run();
})->throws(\RuntimeException::class, 'No user found with email [missing@example.com].');

it('throws when the user has no team', function (): void {
    $user = User::factory()->create();

    (new StressSeeder(email: $user->email, transactionCount: 1))->run();
})->throws(\RuntimeException::class, 'The user has no team. Create a team first, then run this seeder.');

it('seeds accounts, tags, transactions, members, and invitations onto the user team', function (): void {
    [$user, $team] = memberWithTeam();

    (new StressSeeder(
        accountCount: 2,
        memberCount: 1,
        tagCount: 3,
        transactionCount: 12,
        invitationCount: 1,
        email: $user->email,
    ))->run();

    expect($team->accounts()->count())->toBe(2)
        ->and($team->accounts()->whereHas('currencies')->count())->toBe(2);
    expect($team->tags()->count())->toBe(3);
    expect($team->transactions()->count())->toBe(12)
        ->and($team->transactions()->whereHas('tags')->exists())->toBeTrue();
    expect($team->users()->count())->toBe(2);
    expect($team->invitations()->count())->toBe(1);
    expect(Currency::query()->where('code', 'USD')->exists())->toBeTrue();
});
