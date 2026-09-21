<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Paddle\Exceptions\PaddleException;
use WorkOS\Exception\ServerException;

/**
 * Feature tests for \App\Modules\Core\Identity\Http\Controllers\User\AccountController.
 */
describe('index', function (): void {
    it('redirects guests to login', function (): void {
        $this->get(route('identity.users.account.index'))
            ->assertRedirectToRoute('login');
    });

    it('renders the account of the user', function (): void {
        [$user] = actingMember();
        $user->update(['avatar' => 'https://example.com/ada.png', 'name' => 'Ada Lovelace']);

        $this->get(route('identity.users.account.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('identity/users/account/index')
                ->where('user.name', 'Ada Lovelace')
                ->where('user.email', $user->email)
                ->where('user.avatar', 'https://example.com/ada.png'));
    });
});

describe('delete', function (): void {
    it('redirects guests to login', function (): void {
        $this->withHeaders(inertiaModalHeaders())
            ->get(route('identity.users.account.delete'))
            ->assertRedirectToRoute('login');
    });

    it('renders the confirmation with the teams the user is alone on', function (): void {
        [$user, $team] = actingMember();
        $team->update(['name' => 'Acme Family']);

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('identity.users.account.delete'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('identity/users/account/delete')
                ->where('email', $user->email)
                ->has('teams', 1)
                ->where('teams.0.name', 'Acme Family')
                ->where('teams.0.slug', 'acme-family')
                ->where('teams.0.subscribed', false));
    });

    it('flags a team on pro among the teams the user is alone on', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('identity.users.account.delete'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->has('teams', 1)
                ->where('teams.0.subscribed', true));
    });

    it('leaves out the teams the user shares with others', function (): void {
        [$user, $team] = actingMember();
        memberOf($team, Role::Admin);

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('identity.users.account.delete'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->has('teams', 0));
    });
});

describe('destroy', function (): void {
    it('redirects guests to login', function (): void {
        $this->delete(route('identity.users.account.destroy'), ['email' => 'ada@example.com'])
            ->assertRedirectToRoute('login');
    });

    it('requires the typed email to match the account', function (): void {
        [$user] = actingMember();
        fakeAuthKitUserDeletion();

        $this->from(route('identity.users.account.index'))
            ->delete(route('identity.users.account.destroy'), ['email' => 'someone@example.com'])
            ->assertRedirectToRoute('identity.users.account.index')
            ->assertInvalid(['email' => trans('The email does not match your account.')]);

        expect(User::query()->whereKey($user->getKey())->exists())->toBeTrue();
    });

    it('requires an email', function (): void {
        [$user] = actingMember();
        fakeAuthKitUserDeletion();

        $this->delete(route('identity.users.account.destroy'), ['email' => ''])
            ->assertRedirect()
            ->assertInvalid(['email']);

        expect(User::query()->whereKey($user->getKey())->exists())->toBeTrue();
    });

    it('keeps everything when paddle refuses to cancel a subscription', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        fakeAuthKitUserDeletion();
        Http::fake([
            '*/subscriptions/*/cancel' => Http::response(['error' => ['detail' => 'subscription is locked']], 400),
        ]);

        $this->from(route('identity.users.account.index'))
            ->delete(route('identity.users.account.destroy'), ['email' => $user->email])
            ->assertRedirectToRoute('identity.users.account.index');

        expect(User::query()->whereKey($user->getKey())->exists())->toBeTrue()
            ->and(Team::query()->whereKey($team->getKey())->exists())->toBeTrue();

        assertFlashedToast(trans('The subscription could not be cancelled. Try again.'));
        Exceptions::assertReported(PaddleException::class);
    });

    it('keeps everything when workos refuses the deletion', function (): void {
        [$user, $team] = actingMember();
        fakeAuthKitUserDeletion(workOsRefusal());

        $this->from(route('identity.users.account.index'))
            ->delete(route('identity.users.account.destroy'), ['email' => $user->email])
            ->assertRedirectToRoute('identity.users.account.index');

        expect(User::query()->whereKey($user->getKey())->exists())->toBeTrue()
            ->and(Team::query()->whereKey($team->getKey())->exists())->toBeTrue();

        assertFlashedToast(trans('Your account could not be deleted. Try again.'));
        Exceptions::assertReported(ServerException::class);
    });

    it('deletes the account with the teams the user was alone on and logs out', function (): void {
        [$user, $team] = actingMember();
        $shared = Team::factory()->create();
        $user->teams()->attach($shared, ['role' => Role::Owner]);
        memberOf($shared, Role::Admin);
        $account = Account::factory()->for($shared)->create();
        $authKit = fakeAuthKitUserDeletion();

        $this->delete(route('identity.users.account.destroy'), ['email' => $user->email])
            ->assertRedirect('/');

        expect(User::query()->whereKey($user->getKey())->exists())->toBeFalse()
            ->and(Team::query()->whereKey($team->getKey())->exists())->toBeFalse()
            ->and(Team::query()->whereKey($shared->getKey())->exists())->toBeTrue()
            ->and(Account::query()->whereKey($account->getKey())->exists())->toBeTrue()
            ->and($shared->users()->count())->toBe(1)
            ->and($authKit->deleted)->toBe([$user->external_id]);

        $this->assertGuest();
        assertFlashedToast(trans('Account deleted'));
    });
});
