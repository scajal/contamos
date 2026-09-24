<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Enums\User\PreferenceKey;
use App\Modules\Core\Identity\Models\User\Preference;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\assertDatabaseEmpty;
use function Pest\Laravel\get;
use function Pest\Laravel\withUnencryptedCookie;

/**
 * Feature tests for \App\Modules\Support\Http\Middleware\HandleAppearance.
 */
it('paints the first response dark when the cookie asks for it', function (): void {
    withUnencryptedCookie('appearance', 'dark')
        ->get(route('home', ['locale' => 'es']))
        ->assertOk()
        ->assertSee('class="dark"', false);
});

it('leaves the html class off when the cookie asks for light', function (): void {
    withUnencryptedCookie('appearance', 'light')
        ->get(route('home', ['locale' => 'es']))
        ->assertOk()
        ->assertDontSee('class="dark"', false);
});

it('hands an unset appearance to the device', function (): void {
    get(route('home', ['locale' => 'es']))
        ->assertOk()
        ->assertDontSee('class="dark"', false)
        ->assertSee('prefers-color-scheme: dark', false);
});

it('prefers the stored appearance over a stale cookie and rewrites the cookie', function (): void {
    [$user] = actingMember();
    Preference::factory()->for($user)->appearance('dark')->create();

    withUnencryptedCookie('appearance', 'light')
        ->get(route('identity.users.account.index'))
        ->assertSee('class="dark"', false)
        ->assertPlainCookie('appearance', 'dark')
        ->assertInertia(fn (Assert $page): Assert => $page->where('appearance', 'dark'));
});

it('stores the cookie appearance of a member who never stored one', function (): void {
    [$user] = actingMember();

    withUnencryptedCookie('appearance', 'dark')
        ->get(route('identity.users.account.index'))
        ->assertSee('class="dark"', false);

    expect($user->preference(PreferenceKey::Appearance))->toBe('dark');
});

it('stores nothing for a member whose cookie holds an unknown appearance', function (): void {
    actingMember();

    withUnencryptedCookie('appearance', 'sepia')
        ->get(route('identity.users.account.index'))
        ->assertInertia(fn (Assert $page): Assert => $page->where('appearance', 'system'));

    assertDatabaseEmpty(Preference::class);
});

it('keeps signed-out visitors on the cookie alone', function (): void {
    withUnencryptedCookie('appearance', 'dark')
        ->get(route('home', ['locale' => 'es']))
        ->assertSee('class="dark"', false);

    assertDatabaseEmpty(Preference::class);
});
