<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Enums\User\PreferenceKey;
use App\Modules\Core\Identity\Models\User\Preference;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\assertDatabaseEmpty;
use function Pest\Laravel\withUnencryptedCookie;

/**
 * Feature tests for \App\Modules\Support\Http\Middleware\SetLocale.
 */
it('prefers the stored locale over a stale cookie and rewrites the cookie', function (): void {
    [$user] = actingMember();
    Preference::factory()->for($user)->locale('en')->create();

    withUnencryptedCookie('filament_language_switch_locale', 'es')
        ->get(route('identity.users.account.index'))
        ->assertPlainCookie('filament_language_switch_locale', 'en')
        ->assertInertia(fn (Assert $page): Assert => $page->where('locale', 'en'));
});

it('stores the cookie locale of a member who never stored one', function (): void {
    [$user] = actingMember();

    withUnencryptedCookie('filament_language_switch_locale', 'es')
        ->get(route('identity.users.account.index'))
        ->assertInertia(fn (Assert $page): Assert => $page->where('locale', 'es'));

    expect($user->preference(PreferenceKey::Locale))->toBe('es');
});

it('stores nothing for a member whose cookie holds an uninstalled locale', function (): void {
    actingMember();

    withUnencryptedCookie('filament_language_switch_locale', 'fr')
        ->get(route('identity.users.account.index'))
        ->assertOk();

    assertDatabaseEmpty(Preference::class);
});

it('keeps signed-out visitors on the cookie alone', function (): void {
    withUnencryptedCookie('filament_language_switch_locale', 'en')
        ->get(route('login'))
        ->assertPlainCookie('filament_language_switch_locale', 'en');

    assertDatabaseEmpty(Preference::class);
});
