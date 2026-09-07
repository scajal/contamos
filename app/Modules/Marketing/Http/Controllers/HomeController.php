<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Http\Controllers;

use App\Modules\Authentication\Actions\ResolveAuthenticationRedirect;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Core\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Response;

class HomeController
{
    /**
     * Redirect the root path to the homepage of the
     * request's resolved locale.
     */
    public function redirect(Request $request): RedirectResponse
    {
        return to_route('home', ['locale' => App::currentLocale()]);
    }

    /**
     * Show the marketing homepage.
     */
    public function index(Request $request, ResolveAuthenticationRedirect $redirect): Response
    {
        /** @var \App\Modules\Core\Identity\Models\User|null $user */
        $user = $request->user();

        return Inertia::render('home/index', [
            'plans' => $this->plans(),
            'dashboardUrl' => $user instanceof User ? $redirect->forUser($user) : null,
            'alternates' => [
                'en' => route('home', ['locale' => 'en']),
                'es' => route('home', ['locale' => 'es']),
                'default' => route('home.redirect'),
            ],
        ]);
    }

    /**
     * Build the plan comparison shown on the plans section, from
     * the \App\Modules\Billing\Enums\Plan and
     * \App\Modules\Billing\Enums\Feature enums.
     *
     * @return array<int, array{key: string, label: string, features: array<int, array{key: string, label: string, limit: int|null}>}>
     */
    protected function plans(): array
    {
        return array_map(
            fn (Plan $plan): array => [
                'key' => $plan->value,
                'label' => $plan->getLabel(),
                'features' => array_map(
                    fn ($feature): array => [
                        'key' => $feature->value,
                        'label' => $feature->getLabel(),
                        'limit' => $feature->limitOn($plan),
                    ],
                    $plan->features(),
                ),
            ],
            Plan::cases(),
        );
    }
}
