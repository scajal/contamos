<?php

declare(strict_types=1);

use function Pest\Laravel\get;

/**
 * Feature tests for the root Inertia template at resources/views/app.blade.php.
 */
describe('google analytics', function (): void {
    it('embeds the tag in production when a measurement id is configured', function (): void {
        app()['env'] = 'production';
        config()->set('services.google.analytics.measurement_id', 'G-TESTID1234');

        get(route('home', ['locale' => 'en']))
            ->assertOk()
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-TESTID1234', escape: false)
            ->assertSee("gtag('config', \"G-TESTID1234\")", escape: false);
    });

    it('leaves the tag out in production when no measurement id is configured', function (): void {
        app()['env'] = 'production';
        config()->set('services.google.analytics.measurement_id', null);

        get(route('home', ['locale' => 'en']))
            ->assertOk()
            ->assertDontSee('googletagmanager.com', escape: false);
    });

    it('leaves the tag out of every other environment', function (): void {
        config()->set('services.google.analytics.measurement_id', 'G-TESTID1234');

        get(route('home', ['locale' => 'en']))
            ->assertOk()
            ->assertDontSee('googletagmanager.com', escape: false);
    });
});
