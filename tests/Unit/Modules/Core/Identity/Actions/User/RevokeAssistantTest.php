<?php

declare(strict_types=1);

use App\Modules\Authentication\Support\AuthKitAuthorizedApplications;
use App\Modules\Core\Identity\Actions\User\RevokeAssistant;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Mcp\Models\RevokedGrant;
use Illuminate\Support\Facades\Http;

/**
 * Unit tests for \App\Modules\Core\Identity\Actions\User\RevokeAssistant.
 */
beforeEach(function (): void {
    Http::preventStrayRequests();
});

/**
 * An application the user authorized, as
 * \App\Modules\Authentication\Support\AuthKitAuthorizedApplications
 * lists it.
 *
 * @return array{id: string, name: string, client_id: string|null}
 */
function authorizedClaude(): array
{
    return ['id' => 'conn_app_claude', 'name' => 'Claude', 'client_id' => 'claude'];
}

it('throws when a user was not provided', function (): void {
    (new RevokeAssistant(new AuthKitAuthorizedApplications))->using(authorizedClaude());
})->throws(Exception::class, 'An assistant can only be revoked for a user known to WorkOS.');

it('refuses a user workos does not know and revokes nothing', function (): void {
    $user = User::factory()->create(['external_id' => null]);

    expect(fn () => (new RevokeAssistant(new AuthKitAuthorizedApplications))->for($user)->using(authorizedClaude()))
        ->toThrow(Exception::class, 'An assistant can only be revoked for a user known to WorkOS.');

    expect(RevokedGrant::query()->count())->toBe(0);
    Http::assertNothingSent();
});
