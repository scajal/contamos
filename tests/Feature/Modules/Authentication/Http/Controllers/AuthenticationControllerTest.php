<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Models\User;
use WorkOS\Client;
use WorkOS\RequestClient\RequestClientInterface;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\withSession;

/**
 * Feature tests for \App\Modules\Authentication\Http\Controllers\AuthenticationController.
 */

/**
 * Fake the WorkOS HTTP client used by \WorkOS\UserManagement::authenticateWithCode().
 */
function fakeWorkOsAuthentication(string $workosId, string $email): void
{
    Client::setRequestClient(new readonly class($workosId, $email) implements RequestClientInterface
    {
        /**
         * Create a fake WorkOS request client.
         */
        public function __construct(
            private string $workosId,
            private string $email,
        ) {}

        /**
         * Return a successful authentication payload for any request.
         *
         * @param  array<int, string>|null  $headers
         * @param  array<string, mixed>|null  $params
         * @return array{0: string, 1: array<string, string>, 2: int}
         */
        public function request($method, $url, ?array $headers = null, ?array $params = null): array
        {
            $payload = json_encode([
                'user' => [
                    'id' => $this->workosId,
                    'email' => $this->email,
                    'first_name' => 'Ada',
                    'last_name' => 'Lovelace',
                    'profile_picture_url' => 'https://example.com/ada.png',
                ],
                'access_token' => 'access-token',
                'refresh_token' => 'refresh-token',
                'organization_id' => null,
            ], JSON_THROW_ON_ERROR);

            return [$payload, [], 200];
        }
    });
}

it('redirects the root path to login', function (): void {
    get('/')
        ->assertRedirectToRoute('login');
});

describe('login', function (): void {
    it('redirects guests to the WorkOS authorize url', function (): void {
        get(route('login'))
            ->assertRedirect()
            ->assertRedirectContains('/user_management/authorize');
    });

    it('redirects an authenticated user without a team to team creation', function (): void {
        actingAs(User::factory()->create())
            ->get(route('login'))
            ->assertRedirectToRoute('identity.teams.create');
    });

    it('redirects an authenticated user with a team to the dashboard', function (): void {
        [$user, $team] = actingMember();

        get(route('login'))
            ->assertRedirectToRoute('finance.teams.dashboard.index', ['team' => $team->getRouteKey()]);
    });
});

describe('authenticate', function (): void {
    it('creates a user and redirects to team creation', function (): void {
        fakeWorkOsAuthentication('user_123', 'ada@example.com');

        $state = ['state' => 'csrf-token', 'previous_url' => base64_encode('/')];

        withSession(['state' => json_encode($state)])
            ->get(route('authenticate', [
                'code' => 'auth-code',
                'state' => json_encode($state),
            ]))
            ->assertRedirectToRoute('identity.teams.create');

        assertAuthenticated();
        assertDatabaseHas((new User)->getTable(), [
            'email' => 'ada@example.com',
            'external_id' => 'user_123',
            'name' => 'Ada Lovelace',
        ]);
    });

    it('finds an existing user and redirects to the dashboard', function (): void {
        [$user, $team] = memberWithTeam();
        $user->update(['external_id' => 'user_existing']);
        fakeWorkOsAuthentication('user_existing', $user->email);

        $state = ['state' => 'csrf-token', 'previous_url' => base64_encode('/')];

        withSession(['state' => json_encode($state)])
            ->get(route('authenticate', [
                'code' => 'auth-code',
                'state' => json_encode($state),
            ]))
            ->assertRedirectToRoute('finance.teams.dashboard.index', ['team' => $team->getRouteKey()]);

        assertAuthenticatedAs($user->refresh());
    });

    it('redirects to the intended url when the user was sent to login from a protected page', function (): void {
        $user = User::factory()->create(['external_id' => 'user_invited']);
        fakeWorkOsAuthentication('user_invited', $user->email);

        $state = ['state' => 'csrf-token', 'previous_url' => base64_encode('/')];
        $intended = route('identity.invitations.show', ['invitation' => str_repeat('a', 64)]);

        withSession(['state' => json_encode($state), 'url.intended' => $intended])
            ->get(route('authenticate', [
                'code' => 'auth-code',
                'state' => json_encode($state),
            ]))
            ->assertRedirect($intended)
            ->assertSessionMissing('url.intended');
    });

    it('redirects an inactive user to logout', function (): void {
        $user = User::factory()->inactive()->create(['external_id' => 'user_inactive']);
        fakeWorkOsAuthentication('user_inactive', $user->email);

        $state = ['state' => 'csrf-token', 'previous_url' => base64_encode('/')];

        withSession(['state' => json_encode($state)])
            ->get(route('authenticate', [
                'code' => 'auth-code',
                'state' => json_encode($state),
            ]))
            ->assertRedirectToRoute('logout');
    });

    it('returns 403 when the state does not match', function (): void {
        withSession(['state' => json_encode(['state' => 'expected'])])
            ->get(route('authenticate', [
                'code' => 'auth-code',
                'state' => json_encode(['state' => 'other']),
            ]))
            ->assertForbidden();
    });
});

describe('logout', function (): void {
    it('redirects guests to login', function (): void {
        get(route('logout'))
            ->assertRedirectToRoute('login');
    });

    it('logs the user out and redirects home when there is no workos session', function (): void {
        actingMember();

        get(route('logout'))
            ->assertRedirect('/');

        assertGuest();
    });
});
