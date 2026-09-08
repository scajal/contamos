<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Http\Controllers;

use App\Modules\Authentication\Actions\ResolveAuthenticationRedirect;
use App\Modules\Billing\Enums\BillingCycle;
use App\Modules\Billing\Enums\Feature;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Core\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Paddle\Cashier;
use Laravel\Paddle\PricePreview;

class HomeController
{
    /**
     * How long the previewed Pro prices are held before Paddle is asked
     * again. Prices change rarely, so the marketing page should not pay
     * for a round trip on every visit.
     */
    private const int PRICE_CACHE_HOURS = 12;

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
            'dashboardUrl' => $user instanceof User ? $redirect->forUser($user) : null,
            'alternates' => [
                'en' => route('home', ['locale' => 'en']),
                'es' => route('home', ['locale' => 'es']),
                'default' => route('home.redirect'),
            ],
            'plans' => $this->plans(),
        ]);
    }

    /**
     * The advertised plans: the free plan's per-feature limits, read
     * straight from \App\Modules\Billing\Enums\Feature so the page can
     * never drift from what the app enforces, and the Pro prices.
     *
     * All copy is left to the page — only numbers cross the wire — so
     * the section stays translatable on the client like the rest of the
     * marketing site.
     *
     * @return array{free: array{accounts: int|null, members: int|null, transactions: int|null}, pro: array{monthly: string|null, yearly: string|null, monthlyAmount: int|null, yearlyAmount: int|null}}
     */
    private function plans(): array
    {
        return [
            'free' => [
                'accounts' => Feature::AddAccounts->limitOn(Plan::Free),
                'members' => Feature::AddMembers->limitOn(Plan::Free),
                'transactions' => Feature::AddTransactions->limitOn(Plan::Free),
            ],
            'pro' => $this->proPrices(),
        ];
    }

    /**
     * Preview the Pro plan's prices through Paddle, cached.
     *
     * Paddle is the only place the amounts live, so they are fetched
     * rather than duplicated here. A failed preview yields nulls, which
     * the page renders as a "see pricing" state — the homepage must not
     * break because a third party is down, and the failure must not be
     * cached in place of a real price.
     *
     * @return array{monthly: string|null, yearly: string|null, monthlyAmount: int|null, yearlyAmount: int|null}
     */
    private function proPrices(): array
    {
        /** @var array{monthly: string|null, yearly: string|null, monthlyAmount: int|null, yearlyAmount: int|null} $prices */
        $prices = rescue(fn (): array => Cache::remember(
            'marketing.plans.pro-prices',
            now()->addHours(self::PRICE_CACHE_HOURS),
            function (): array {
                // Previewed one cycle at a time: Paddle returns the
                // items in an arbitrary order and the preview exposes no
                // typed accessor for the price ID to match them back up.
                $monthly = $this->previewPrice(Plan::Pro->priceId(BillingCycle::Monthly));
                $yearly = $this->previewPrice(Plan::Pro->priceId(BillingCycle::Yearly));

                return [
                    'monthly' => $monthly?->total(),
                    'yearly' => $yearly?->total(),
                    'monthlyAmount' => $this->minorUnits($monthly),
                    'yearlyAmount' => $this->minorUnits($yearly),
                ];
            }
        ), [
            'monthly' => null,
            'yearly' => null,
            'monthlyAmount' => null,
            'yearlyAmount' => null,
        ]);

        return $prices;
    }

    /**
     * Preview a single Paddle price.
     */
    private function previewPrice(string $priceId): ?PricePreview
    {
        $preview = Cashier::previewPrices([$priceId])->first();

        return $preview instanceof PricePreview ? $preview : null;
    }

    /**
     * The preview's total in minor units, so the page can work out what
     * the yearly cycle saves without parsing formatted money.
     */
    private function minorUnits(?PricePreview $preview): ?int
    {
        $total = $preview?->rawTotal();

        return is_numeric($total) ? (int) $total : null;
    }
}
