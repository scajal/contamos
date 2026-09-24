<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Enums\User\PreferenceKey;

/**
 * Unit tests for \App\Modules\Core\Identity\Enums\User\PreferenceKey.
 */
it('labels each preference', function (PreferenceKey $key, string $label): void {
    expect($key->getLabel())->toBe($label);
})->with([
    'appearance' => [PreferenceKey::Appearance, 'Theme'],
    'locale' => [PreferenceKey::Locale, 'Language'],
]);

it('accepts only the values the preference offers', function (PreferenceKey $key, mixed $value, bool $accepted): void {
    expect($key->accepts($value))->toBe($accepted);
})->with([
    'installed locale' => [PreferenceKey::Locale, 'es', true],
    'uninstalled locale' => [PreferenceKey::Locale, 'fr', false],
    'system appearance' => [PreferenceKey::Appearance, 'system', true],
    'unknown appearance' => [PreferenceKey::Appearance, 'sepia', false],
    'non-string value' => [PreferenceKey::Appearance, ['dark'], false],
]);

it('builds a cookie the browser may overwrite', function (): void {
    $cookie = PreferenceKey::Locale->cookie('es');

    expect($cookie->getName())->toBe('filament_language_switch_locale')
        ->and($cookie->getValue())->toBe('es')
        ->and($cookie->isHttpOnly())->toBeFalse();
});
