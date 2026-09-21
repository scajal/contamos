<?php

declare(strict_types=1);

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
