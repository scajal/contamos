<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Marketing\Enums\Docs\Page;
use App\Modules\Mcp\Helpers\AssistantGuide;
use App\Modules\Mcp\Http\Middleware\AuthenticateMcpToken;
use App\Modules\Mcp\Models\RevokedGrant;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature as Pennant;

use function Pest\Laravel\actingAs;

/**
 * Feature tests for \App\Modules\Core\Identity\Http\Controllers\User\AssistantController.
 */

/**
 * Fake the WorkOS list of applications the user authorized.
 *
 * @param  array<int, array<string, mixed>>  $applications
 */
function fakeAuthorizedApplications(User $user, array $applications): void
{
    Http::fake([
        "api.workos.com/user_management/users/{$user->external_id}/authorized_applications*" => function (Request $request) use ($applications) {
            if ($request->method() === 'DELETE') {
                return Http::response(status: 204);
            }

            return Http::response([
                'object' => 'list',
                'data' => $applications,
                'list_metadata' => ['before' => null, 'after' => null],
            ]);
        },
    ]);
}

/**
 * A WorkOS authorized application entry for Claude.
 *
 * @return array<string, mixed>
 */
function claudeApplication(): array
{
    return [
        'object' => 'authorized_connect_application',
        'id' => 'authorized_connect_app_01',
        'granted_scopes' => ['openid', 'profile'],
        'application' => [
            'object' => 'connect_application',
            'id' => 'conn_app_claude',
            'client_id' => 'https://claude.ai/oauth/mcp-oauth-client-metadata',
            'name' => 'Claude',
        ],
    ];
}

describe('index', function (): void {
    it('redirects guests to login', function (): void {
        $this->get(route('identity.users.assistants.index'))
            ->assertRedirectToRoute('login');
    });

    it('renders the server address and the connected assistants', function (): void {
        [$user] = actingMember();
        fakeAuthorizedApplications($user, [claudeApplication()]);

        $this->get(route('identity.users.assistants.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('identity/users/assistants/index')
                ->where('server_url', AuthenticateMcpToken::resource())
                ->missing('connected_apps')
                ->loadDeferredProps(fn (Assert $reload): Assert => $reload
                    ->where('connected_apps.failed', false)
                    ->has('connected_apps.items', 1)
                    ->where('connected_apps.items.0.id', 'conn_app_claude')
                    ->where('connected_apps.items.0.name', 'Claude')));

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization'));
    });

    it('shows the connection steps and example prompts the docs share', function (): void {
        [$user] = actingMember();
        fakeAuthorizedApplications($user, []);

        $this->get(route('identity.users.assistants.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('guides', AssistantGuide::guides())
                ->where('prompts', AssistantGuide::prompts())
                ->where('prompts', fn (\Illuminate\Support\Collection $prompts): bool => $prompts->where('pro', true)->count() === 1)
                ->where('guideUrl', route('docs.show', ['locale' => 'en', 'page' => Page::AiAssistants->value])));
    });

    it('links the guide in the locale of the user', function (): void {
        [$user] = actingMember();
        fakeAuthorizedApplications($user, []);

        $this->withUnencryptedCookie('filament_language_switch_locale', 'es')
            ->get(route('identity.users.assistants.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('guideUrl', route('docs.show', ['locale' => 'es', 'page' => Page::AiAssistants->value]))
                ->where('prompts.0.prompt', '¿Cuáles son los saldos de mi equipo, en cada moneda?'));
    });

    it('reads every page of connected assistants', function (): void {
        [$user] = actingMember();
        $chatgpt = ['object' => 'authorized_connect_application', 'id' => 'authorized_connect_app_02', 'application' => ['id' => 'conn_app_chatgpt', 'client_id' => 'chatgpt', 'name' => 'ChatGPT']];
        Http::fake(function (Request $request) use ($chatgpt) {
            return ($request->data()['after'] ?? null) === 'authorized_connect_app_01'
                ? Http::response(['data' => [$chatgpt], 'list_metadata' => ['before' => 'authorized_connect_app_02', 'after' => null]])
                : Http::response(['data' => [claudeApplication()], 'list_metadata' => ['before' => null, 'after' => 'authorized_connect_app_01']]);
        });

        $this->get(route('identity.users.assistants.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->loadDeferredProps(fn (Assert $reload): Assert => $reload
                    ->where('connected_apps.items.0.name', 'Claude')
                    ->where('connected_apps.items.1.name', 'ChatGPT')));
    });

    it('shows an empty list when WorkOS cannot answer', function (): void {
        actingMember();
        Http::fake(['api.workos.com/*' => Http::response(['message' => 'Unavailable'], 503)]);

        $this->get(route('identity.users.assistants.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->loadDeferredProps(fn (Assert $reload): Assert => $reload
                    ->where('connected_apps.failed', true)
                    ->where('connected_apps.items', [])));

        Exceptions::assertReported(RequestException::class);
    });

    it('lists no assistants for a user WorkOS does not know', function (): void {
        [$user] = actingMember();
        $user->forceFill(['external_id' => null])->save();

        $this->get(route('identity.users.assistants.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->loadDeferredProps(fn (Assert $reload): Assert => $reload
                    ->where('connected_apps.failed', false)
                    ->where('connected_apps.items', [])));

        Http::assertNothingSent();
    });

    it('offers the upgrade when the plan leaves assistants out', function (): void {
        [$user, $team] = actingMember();
        Pennant::for($team)->deactivate(Feature::UseMcp);
        fakeAuthorizedApplications($user, []);

        $this->get(route('identity.users.assistants.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('is_locked', true));
    });

    it('offers no upgrade on a free team', function (): void {
        [$user, $team] = actingMember();
        fakeAuthorizedApplications($user, []);

        $this->get(route('identity.users.assistants.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('is_locked', false));
    });
});

describe('destroy', function (): void {
    it('redirects guests to login', function (): void {
        $this->delete(route('identity.users.assistants.destroy', ['application' => 'conn_app_claude']))
            ->assertRedirectToRoute('login');
    });

    it('revokes the assistant at WorkOS and refuses its tokens at once', function (): void {
        [$user] = mcpMember();
        fakeAuthKitJwks();
        fakeAuthorizedApplications($user, [claudeApplication()]);
        $token = mcpToken($user, ['iat' => now()->subMinute()->getTimestamp()]);

        mcpCall($token)->assertOk();

        actingAs($user)
            ->from(route('identity.users.assistants.index'))
            ->delete(route('identity.users.assistants.destroy', ['application' => 'conn_app_claude']))
            ->assertRedirectToRoute('identity.users.assistants.index');

        assertFlashedToast(trans('Assistant disconnected'));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
            && $request->url() === "https://api.workos.com/user_management/users/{$user->external_id}/authorized_applications/conn_app_claude");

        expect(RevokedGrant::query()->whereBelongsTo($user)->sole())
            ->client_id->toBe('https://claude.ai/oauth/mcp-oauth-client-metadata')
            ->session_id->toBeNull();

        mcpCall($token)->assertUnauthorized();
    });

    it('refuses an application the user did not authorize', function (): void {
        [$user] = actingMember();
        fakeAuthorizedApplications($user, [claudeApplication()]);

        $this->delete(route('identity.users.assistants.destroy', ['application' => 'conn_app_other']))
            ->assertNotFound();

        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'DELETE');
        expect(RevokedGrant::query()->count())->toBe(0);
    });

    it('changes nothing when WorkOS cannot list the assistants', function (): void {
        [$user] = actingMember();
        Http::fake(['api.workos.com/*' => Http::response(['message' => 'Unavailable'], 503)]);

        $this->from(route('identity.users.assistants.index'))
            ->delete(route('identity.users.assistants.destroy', ['application' => 'conn_app_claude']))
            ->assertRedirectToRoute('identity.users.assistants.index');

        assertFlashedToast(trans('The assistant could not be disconnected. Try again.'));

        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'DELETE');
        expect(RevokedGrant::query()->count())->toBe(0);
        Exceptions::assertReported(RequestException::class);
    });

    it('refuses the assistant here even when WorkOS refuses the revocation', function (): void {
        [$user] = actingMember();
        Http::fake([
            "api.workos.com/user_management/users/{$user->external_id}/authorized_applications/*" => Http::response(['message' => 'Unavailable'], 503),
            "api.workos.com/user_management/users/{$user->external_id}/authorized_applications*" => Http::response(['data' => [claudeApplication()]]),
        ]);

        $this->from(route('identity.users.assistants.index'))
            ->delete(route('identity.users.assistants.destroy', ['application' => 'conn_app_claude']))
            ->assertRedirectToRoute('identity.users.assistants.index');

        assertFlashedToast(trans('The assistant can no longer access your account, but it could not be removed from the list. Try again.'));

        expect(RevokedGrant::query()->whereBelongsTo($user)->count())->toBe(1);
        Exceptions::assertReported(RequestException::class);
    });
});
