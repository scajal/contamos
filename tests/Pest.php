<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
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
 * Create a \App\Modules\Core\Identity\Models\User that belongs to a
 * \App\Modules\Core\Identity\Models\Team and switch to that team.
 *
 * @return array{0: \App\Modules\Core\Identity\Models\User, 1: \App\Modules\Core\Identity\Models\Team}
 */
function memberWithTeam(): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $user->teams()->attach($team);
    $user->switchTeam($team);

    return [$user, $team];
}

/**
 * Create a \App\Modules\Core\Identity\Models\User that belongs to a
 * \App\Modules\Core\Identity\Models\Team and authenticate as that user.
 *
 * @return array{0: \App\Modules\Core\Identity\Models\User, 1: \App\Modules\Core\Identity\Models\Team}
 */
function actingMember(): array
{
    [$user, $team] = memberWithTeam();

    actingAs($user);

    return [$user, $team];
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
