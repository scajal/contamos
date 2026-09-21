<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\Team\Activity;
use App\Modules\Core\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Tests\TestCase;

use function Pest\Laravel\actingAs;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->in('Feature', 'Unit');

pest()->beforeEach(function (): void {
    Http::preventStrayRequests();
    Sleep::fake(syncWithCarbon: true);
    Exceptions::fake();
})->in('Feature');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Empty the activity log, so a test asserts the entries the
 * request under it wrote and not the ones its fixtures did.
 */
function clearActivities(): void
{
    Activity::query()->delete();
}

/**
 * Get the entries of a \App\Modules\Core\Identity\Models\Team
 * activity log, oldest first, narrowed to one event when one
 * is named.
 *
 * @return \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Identity\Models\Team\Activity>
 */
function activitiesOf(Team $team, ?string $event = null): Collection
{
    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Identity\Models\Team\Activity> $activities */
    $activities = $team->activities()
        ->when($event !== null, fn (Builder $query): Builder => $query->where('event', $event))
        ->orderBy(new Activity()->getKeyName())
        ->get();

    return $activities;
}

/**
 * Create a \App\Modules\Core\Identity\Models\User that belongs to a
 * \App\Modules\Core\Identity\Models\Team with the given role and
 * switch to that team.
 *
 * @return array{0: \App\Modules\Core\Identity\Models\User, 1: \App\Modules\Core\Identity\Models\Team}
 */
function memberWithTeam(Role $role = Role::Owner): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $user->teams()->attach($team, ['role' => $role]);
    $user->switchTeam($team);

    // A team without an owner cannot exist, so one is added
    // whenever the member under test is not the owner.
    if ($role !== Role::Owner) {
        $team->users()->attach(User::factory()->create(), ['role' => Role::Owner]);
    }

    return [$user, $team];
}

/**
 * Create a \App\Modules\Core\Identity\Models\User that belongs to a
 * \App\Modules\Core\Identity\Models\Team with the given role and
 * authenticate as that user.
 *
 * @return array{0: \App\Modules\Core\Identity\Models\User, 1: \App\Modules\Core\Identity\Models\Team}
 */
function actingMember(Role $role = Role::Owner): array
{
    [$user, $team] = memberWithTeam($role);

    actingAs($user);

    return [$user, $team];
}

/**
 * Add a \App\Modules\Core\Identity\Models\User to the given
 * \App\Modules\Core\Identity\Models\Team with the given role.
 */
function memberOf(Team $team, Role $role = Role::Admin, ?User $user = null): User
{
    $user ??= User::factory()->create();

    $user->teams()->attach($team, ['role' => $role]);

    return $user;
}

/**
 * Subscribe a \App\Modules\Core\Identity\Models\Team to the pro plan
 * with an active Paddle subscription, without calling Paddle.
 */
function subscribeToPro(Team $team): void
{
    /** @var \App\Modules\Billing\Models\Subscription $subscription */
    $subscription = $team->subscriptions()->create([
        'type' => 'default',
        'paddle_id' => 'sub_'.Str::lower(Str::random(12)),
        'status' => \Laravel\Paddle\Subscription::STATUS_ACTIVE,
    ]);

    $subscription->items()->create([
        'product_id' => \App\Modules\Billing\Enums\Plan::Pro->productId(),
        'price_id' => \App\Modules\Billing\Enums\Plan::Pro->priceId(\App\Modules\Billing\Enums\BillingCycle::Monthly),
        'status' => \Laravel\Paddle\Subscription::STATUS_ACTIVE,
        'quantity' => 1,
    ]);

    $team->unsetRelation('subscriptions');
}

/**
 * HTTP headers that load an \InertiaUI\Modal\Modal as a modal XHR
 * instead of the dual-request base-page visit.
 *
 * @return array<string, string>
 */
function inertiaModalHeaders(): array
{
    return [\InertiaUI\Modal\Modal::HEADER_MODAL => '1'];
}

/**
 * Assert that the session contains an Inertia toast with the given message.
 */
function assertFlashedToast(string $message): void
{
    $flash = session()->get(\Inertia\Support\SessionKey::FLASH_DATA, []);

    if (! is_array($flash) || ! isset($flash['toast']) || ! is_array($flash['toast'])) {
        throw new InvalidArgumentException('Expected an Inertia toast flash payload.');
    }

    expect($flash['toast'])
        ->toHaveKeys(['id', 'message'])
        ->and($flash['toast']['message'])->toBe($message);
}

/**
 * Get the balance a \App\Modules\Core\Finance\Models\Team\Account holds
 * for a \App\Modules\Core\Catalog\Models\Currency.
 */
function balanceOf(Account $account, Currency $currency): float
{
    return (float) $account->currencies()->whereKey($currency->getKey())->firstOrFail()->pivot->balance;
}

/**
 * Build the attributes of a transfer between the given sides, as the
 * transfer requests hand them to the transfer actions.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function transferAttributes(Account $origin, Account $destination, Currency $originCurrency, Currency $destinationCurrency, array $overrides = []): array
{
    return [
        'amount_received' => '25.00',
        'amount_sent' => '25.00',
        'description' => 'Rent savings',
        'destination_account_id' => $destination->getKey(),
        'destination_currency_id' => $destinationCurrency->getKey(),
        'destination_fee' => null,
        'destination_quote' => '1',
        'occurred_at' => '2024-01-01 00:00:00',
        'origin_account_id' => $origin->getKey(),
        'origin_currency_id' => $originCurrency->getKey(),
        'origin_fee' => null,
        'origin_quote' => '1',
        ...$overrides,
    ];
}

/**
 * Create a \App\Modules\Core\Identity\Models\Team with two accounts
 * sharing a \App\Modules\Core\Catalog\Models\Currency.
 *
 * @return array{0: \App\Modules\Core\Identity\Models\Team, 1: \App\Modules\Core\Finance\Models\Team\Account, 2: \App\Modules\Core\Finance\Models\Team\Account, 3: \App\Modules\Core\Catalog\Models\Currency}
 */
function transferSides(string $originBalance = '100.00', string $destinationBalance = '0.00'): array
{
    $team = Team::factory()->create();
    $currency = Currency::factory()->fiat()->create(['decimal_places' => 2]);
    $origin = Account::factory()->bank()->for($team)->create();
    $destination = Account::factory()->cash()->for($team)->create();
    $origin->currencies()->attach($currency, ['balance' => $originBalance]);
    $destination->currencies()->attach($currency, ['balance' => $destinationBalance]);

    return [$team, $origin, $destination, $currency];
}
