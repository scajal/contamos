<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Http\Controllers;

use App\Modules\Authentication\Actions\ResolveAuthenticationRedirect;
use App\Modules\Billing\Enums\BillingCycle;
use App\Modules\Billing\Enums\Feature;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Core\Identity\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Response;

class HomeController
{
    /**
     * Redirect the root path to the homepage of the
     * request's resolved locale.
     */
    public function redirect(): RedirectResponse
    {
        return to_route('home', ['locale' => App::currentLocale()]);
    }

    /**
     * Show the marketing homepage.
     */
    public function index(#[CurrentUser] ?User $user, ResolveAuthenticationRedirect $redirect): Response
    {
        return Inertia::render('marketing/home/index', [
            'dashboardUrl' => $user instanceof User ? $redirect->forUser($user) : null,
            'alternates' => [
                'en' => route('home', ['locale' => 'en']),
                'es' => route('home', ['locale' => 'es']),
                'default' => route('home.redirect'),
            ],
            // The plans are described from the same enum the app gates
            // on, so what the page promises is what a team actually
            // gets, and a limit can only be changed in one place.
            'features' => collect(Feature::cases())
                ->map(fn (Feature $feature): array => [
                    'feature' => $feature->value,
                    'label' => $feature->getLabel(),
                    'free' => $feature->valueOn(Plan::Free),
                    'pro' => $feature->valueOn(Plan::Pro),
                ])
                ->values(),
            // Previewed from Paddle and cached, so the price on the
            // page is the price at checkout. Every field is null when
            // Paddle cannot be reached, and the section then offers the
            // plan without a figure rather than an invented one.
            'prices' => [
                BillingCycle::Monthly->value => Plan::Pro->price(BillingCycle::Monthly),
                BillingCycle::Yearly->value => Plan::Pro->price(BillingCycle::Yearly),
            ],
        ]);
    }
}
