<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Actions\Team\DeleteTeam;
use App\Modules\Core\Identity\Actions\User\DeleteUser;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\Team\TeamInvitation;
use App\Modules\Core\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Paddle\Exceptions\PaddleException;
use Laravel\Pennant\Feature as Pennant;
use WorkOS\Exception\ServerException;

/**
 * Unit tests for \App\Modules\Core\Identity\Actions\User\DeleteUser.
 */
it('deletes the user with the teams they were alone on', function (): void {
    [$user, $team] = memberWithTeam();
    Account::factory()->for($team)->create();
    Tag::factory()->for($team)->create();
    TeamInvitation::factory()->for($team)->create(['invited_by' => $user->getKey()]);

    new DeleteUser(fakeAuthKitUserDeletion(), new DeleteTeam)->using($user);

    expect(User::query()->whereKey($user->getKey())->exists())->toBeFalse()
        ->and(Team::query()->whereKey($team->getKey())->exists())->toBeFalse()
        ->and(Account::query()->where('team_id', $team->getKey())->exists())->toBeFalse()
        ->and(Tag::query()->where('team_id', $team->getKey())->exists())->toBeFalse()
        ->and(TeamInvitation::query()->where('team_id', $team->getKey())->exists())->toBeFalse();
});

it('keeps a team shared with other members and drops only the membership', function (): void {
    [$user, $team] = memberWithTeam();
    $other = memberOf($team, Role::Admin);
    $account = Account::factory()->for($team)->create();
    $tag = Tag::factory()->for($team)->create();

    new DeleteUser(fakeAuthKitUserDeletion(), new DeleteTeam)->using($user);

    expect(Team::query()->whereKey($team->getKey())->exists())->toBeTrue()
        ->and(Account::query()->whereKey($account->getKey())->exists())->toBeTrue()
        ->and(Tag::query()->whereKey($tag->getKey())->exists())->toBeTrue()
        ->and($team->users()->count())->toBe(1)
        ->and($team->fresh()?->users()->whereKey($other->getKey())->exists())->toBeTrue();
});

it('keeps the transactions the user recorded on a shared team without their author', function (): void {
    [$user, $team] = memberWithTeam();
    memberOf($team, Role::Admin);
    $account = Account::factory()->for($team)->create();
    $transaction = Transaction::factory()->for($team)->for($account)->create(['user_id' => $user->getKey()]);

    new DeleteUser(fakeAuthKitUserDeletion(), new DeleteTeam)->using($user);

    expect(Transaction::query()->whereKey($transaction->getKey())->exists())->toBeTrue()
        ->and($transaction->fresh()?->user_id)->toBeNull();
});

it('drops the invitations the user sent on a shared team', function (): void {
    [$user, $team] = memberWithTeam();
    memberOf($team, Role::Admin);
    $invitation = TeamInvitation::factory()->for($team)->create(['invited_by' => $user->getKey()]);

    new DeleteUser(fakeAuthKitUserDeletion(), new DeleteTeam)->using($user);

    expect(TeamInvitation::query()->whereKey($invitation->getKey())->exists())->toBeFalse();
});

it('purges the sessions of the user on every device', function (): void {
    [$user] = memberWithTeam();
    $other = User::factory()->create();
    session()->getHandler();
    DB::table(config()->string('session.table'))->insert([
        ['id' => 'session-one', 'user_id' => $user->getKey(), 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => time()],
        ['id' => 'session-two', 'user_id' => $other->getKey(), 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => time()],
    ]);

    new DeleteUser(fakeAuthKitUserDeletion(), new DeleteTeam)->using($user);

    expect(DB::table(config()->string('session.table'))->where('user_id', $user->getKey())->exists())->toBeFalse()
        ->and(DB::table(config()->string('session.table'))->where('user_id', $other->getKey())->exists())->toBeTrue();
});

it('purges the feature flag rows scoped to the user', function (): void {
    // The array store keeps nothing, so the rows the deletion
    // has to purge only exist on the database store.
    config(['pennant.default' => 'database']);
    Pennant::forgetDrivers();

    [$user] = memberWithTeam();
    Pennant::for($user)->activate(Feature::AddTeams->value);

    expect(DB::table('features')->where('scope', Pennant::serializeScope($user))->exists())->toBeTrue();

    new DeleteUser(fakeAuthKitUserDeletion(), new DeleteTeam)->using($user);

    expect(DB::table('features')->where('scope', Pennant::serializeScope($user))->exists())->toBeFalse();
});

it('deletes the user at workos', function (): void {
    [$user] = memberWithTeam();
    $user->update(['external_id' => 'user_123']);
    $authKit = fakeAuthKitUserDeletion();

    new DeleteUser($authKit, new DeleteTeam)->using($user);

    expect($authKit->deleted)->toBe(['user_123']);
});

it('skips workos when the user has no identifier there', function (): void {
    [$user] = memberWithTeam();
    $user->update(['external_id' => null]);
    $authKit = fakeAuthKitUserDeletion();

    new DeleteUser($authKit, new DeleteTeam)->using($user);

    expect($authKit->deleted)->toBe([])
        ->and(User::query()->whereKey($user->getKey())->exists())->toBeFalse();
});

it('keeps everything when workos refuses the deletion', function (): void {
    [$user, $team] = memberWithTeam();
    $user->update(['external_id' => 'user_123']);
    $account = Account::factory()->for($team)->create();

    expect(fn (): null => new DeleteUser(fakeAuthKitUserDeletion(workOsRefusal()), new DeleteTeam)->using($user))
        ->toThrow(ServerException::class);

    expect(User::query()->whereKey($user->getKey())->exists())->toBeTrue()
        ->and(Team::query()->whereKey($team->getKey())->exists())->toBeTrue()
        ->and(Account::query()->whereKey($account->getKey())->exists())->toBeTrue();
});

it('cancels the subscription of a solo team on paddle', function (): void {
    [$user, $team] = memberWithTeam();
    subscribeToPro($team);
    Http::fake([
        '*/subscriptions/*/cancel' => Http::response(['data' => [
            'status' => \Laravel\Paddle\Subscription::STATUS_CANCELED,
            'canceled_at' => '2026-09-09T12:00:00Z',
        ]]),
    ]);

    new DeleteUser(fakeAuthKitUserDeletion(), new DeleteTeam)->using($user);

    Http::assertSentCount(1);

    expect(User::query()->whereKey($user->getKey())->exists())->toBeFalse()
        ->and(Team::query()->whereKey($team->getKey())->exists())->toBeFalse();
});

it('keeps everything when paddle refuses the cancellation', function (): void {
    [$user, $team] = memberWithTeam();
    subscribeToPro($team);
    $authKit = fakeAuthKitUserDeletion();
    Http::fake([
        '*/subscriptions/*/cancel' => Http::response(['error' => ['detail' => 'subscription is locked']], 400),
    ]);

    expect(fn (): null => new DeleteUser($authKit, new DeleteTeam)->using($user))
        ->toThrow(PaddleException::class);

    expect(User::query()->whereKey($user->getKey())->exists())->toBeTrue()
        ->and(Team::query()->whereKey($team->getKey())->exists())->toBeTrue()
        ->and($authKit->deleted)->toBe([]);
});
