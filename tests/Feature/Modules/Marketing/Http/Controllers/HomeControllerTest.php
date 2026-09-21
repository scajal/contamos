<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Feature;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Core\Identity\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\get;
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
        withUnencryptedCookie('filament_language_switch_locale', 'es')
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
                ->component('marketing/home/index')
                ->where('user', null)
                ->where('locale', 'en')
                ->where('dashboardUrl', null));
    });

    it('leaves the appearance to the device when no cookie was written', function (): void {
        get(route('home', ['locale' => 'en']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('appearance', 'system'));
    });

    it('reads a dark appearance from its unencrypted cookie', function (): void {
        withUnencryptedCookie('appearance', 'dark')
            ->get(route('home', ['locale' => 'en']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('appearance', 'dark'));
    });

    it('ignores an appearance cookie it does not know', function (): void {
        withUnencryptedCookie('appearance', 'sepia')
            ->get(route('home', ['locale' => 'en']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('appearance', 'system'));
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
            ->assertPlainCookie('filament_language_switch_locale', 'es')
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('locale', 'es'));
    });

    it('404s for an unsupported locale', function (): void {
        get(route('home', ['locale' => 'fr']))
            ->assertNotFound();
    });
});

describe('public files', function (): void {
    it('publishes an llms.txt', function (): void {
        expect(file_exists(public_path('llms.txt')))->toBeTrue();
    });
});

it('describes both plans from the enum the app gates on', function (): void {
    get(route('home', ['locale' => 'en']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
            ->has('features', count(Feature::cases()))
            ->where('features.0.feature', Feature::AddAccounts->value)
            ->where('features.0.label', trans('Accounts per team'))
            ->where('features.0.free', Feature::AddAccounts->valueOn(Plan::Free))
            ->where('features.0.pro', Feature::AddAccounts->valueOn(Plan::Pro)));
});

it('offers the pro price for both billing cycles', function (): void {
    get(route('home', ['locale' => 'en']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
            ->has('prices.monthly', fn (Assert $price): \Inertia\Testing\AssertableInertia => $price
                ->hasAll('amount', 'currency', 'formatted'))
            ->has('prices.yearly', fn (Assert $price): \Inertia\Testing\AssertableInertia => $price
                ->hasAll('amount', 'currency', 'formatted')));
});
