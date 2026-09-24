<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Enums\User\PreferenceKey;
use App\Modules\Core\Identity\Models\User\Preference;

use function Pest\Laravel\assertDatabaseEmpty;

/**
 * Feature tests for \App\Modules\Core\Identity\Http\Controllers\User\PreferenceController.
 */
describe('update', function (): void {
    it('redirects guests to login without storing anything', function (): void {
        $this->patch(route('identity.users.preferences.update'), ['key' => 'appearance', 'value' => 'dark'])
            ->assertRedirectToRoute('login');

        assertDatabaseEmpty(Preference::class);
    });

    it('stores the chosen language and sets its cookie', function (): void {
        [$user] = actingMember();

        $this->patch(route('identity.users.preferences.update'), ['key' => 'locale', 'value' => 'es'])
            ->assertNoContent()
            ->assertPlainCookie('filament_language_switch_locale', 'es');

        expect($user->preference(PreferenceKey::Locale))->toBe('es');
    });

    it('stores the chosen appearance and sets its cookie', function (): void {
        [$user] = actingMember();

        $this->patch(route('identity.users.preferences.update'), ['key' => 'appearance', 'value' => 'system'])
            ->assertNoContent()
            ->assertPlainCookie('appearance', 'system');

        expect($user->preference(PreferenceKey::Appearance))->toBe('system');
    });

    it('replaces the value stored before', function (): void {
        [$user] = actingMember();
        Preference::factory()->for($user)->appearance('dark')->create();

        $this->patch(route('identity.users.preferences.update'), ['key' => 'appearance', 'value' => 'light'])
            ->assertNoContent();

        expect($user->preferences()->count())->toBe(1)
            ->and($user->preference(PreferenceKey::Appearance))->toBe('light');
    });

    it('rejects an unknown preference', function (): void {
        actingMember();

        $this->patch(route('identity.users.preferences.update'), ['key' => 'timezone', 'value' => 'dark'])
            ->assertInvalid(['key' => 'The selected key is invalid.']);

        assertDatabaseEmpty(Preference::class);
    });

    it('rejects a value the preference does not accept', function (string $key, string $value): void {
        actingMember();

        $this->patch(route('identity.users.preferences.update'), ['key' => $key, 'value' => $value])
            ->assertInvalid(['value' => 'The selected value is invalid.']);

        assertDatabaseEmpty(Preference::class);
    })->with([
        'uninstalled locale' => ['locale', 'fr'],
        'unknown appearance' => ['appearance', 'sepia'],
    ]);

    it('requires a preference and a value', function (): void {
        actingMember();

        $this->patch(route('identity.users.preferences.update'), [])
            ->assertInvalid(['key', 'value']);

        assertDatabaseEmpty(Preference::class);
    });
});
