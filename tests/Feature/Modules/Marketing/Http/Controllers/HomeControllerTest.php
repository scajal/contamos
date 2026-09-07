<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\get;
use function Pest\Laravel\withCookie;
use function Pest\Laravel\withHeaders;
use function Pest\Laravel\withUnencryptedCookie;

/**
 * Feature tests for \App\Modules\Marketing\Http\Controllers\HomeController.
 */
describe('redirect', function (): void {
    it('redirects the root path to the english homepage by default', function (): void {
        get('/')
            ->assertRedirect('/en');
    });

    it('redirects to the homepage of the locale cookie', function (): void {
        withCookie('locale', 'es')
            ->get('/')
            ->assertRedirect('/es');
    });

    it('redirects to the homepage of the preferred browser language', function (): void {
        withHeaders(['Accept-Language' => 'es-ES,es;q=0.9'])
            ->get('/')
            ->assertRedirect('/es');
    });
});

describe('index', function (): void {
    it('renders the homepage for a guest', function (): void {
        get(route('home', ['locale' => 'en']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('home/index')
                ->where('user', null)
                ->where('locale', 'en')
                ->where('dashboardUrl', null)
                ->missing('plans'));
    });

    it('defaults the marketing appearance to dark', function (): void {
        get(route('home', ['locale' => 'en']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('appearance', 'dark'));
    });

    it('reads the appearance from its unencrypted cookie', function (): void {
        withUnencryptedCookie('appearance', 'light')
            ->get(route('home', ['locale' => 'en']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('appearance', 'light'));
    });

    it('resolves the dashboard url for a member', function (): void {
        [$user, $team] = actingMember();

        get(route('home', ['locale' => 'en']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('dashboardUrl', route('finance.teams.dashboard.index', ['team' => $team->getRouteKey()])));
    });

    it('resolves team creation for an authenticated user without a team', function (): void {
        $this->actingAs(User::factory()->create());

        get(route('home', ['locale' => 'en']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('dashboardUrl', route('identity.teams.create')));
    });

    it('renders the spanish homepage and sets the locale cookie', function (): void {
        get(route('home', ['locale' => 'es']))
            ->assertOk()
            ->assertSee('lang="es"', false)
            ->assertCookie('locale')
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('locale', 'es'));
    });

    it('404s for an unsupported locale', function (): void {
        get(route('home', ['locale' => 'fr']))
            ->assertNotFound();
    });
});

describe('public files', function (): void {
    it('publishes a sitemap with both locale urls', function (): void {
        $sitemap = file_get_contents(public_path('sitemap.xml'));

        expect($sitemap)
            ->toContain('/en')
            ->toContain('/es');
    });

    it('publishes an llms.txt', function (): void {
        expect(file_exists(public_path('llms.txt')))->toBeTrue();
    });

    it('advertises the sitemap in robots.txt', function (): void {
        expect(file_get_contents(public_path('robots.txt')))->toContain('Sitemap:');
    });
});
