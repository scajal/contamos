<?php

declare(strict_types=1);

namespace App\Modules\Billing\Providers;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Billing\Models\Customer;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\Subscription\SubscriptionItem;
use App\Modules\Billing\Models\Transaction;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\Team\TeamInvitation;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Objects\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Laravel\Paddle\Cashier;
use Laravel\Pennant\Feature as Pennant;
use Laravel\Pennant\Middleware\EnsureFeaturesAreActive;
use Symfony\Component\HttpFoundation\Response;

class BillingServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->configureModels();
        $this->configureFeatures();
    }

    /**
     * Configure the models that the billing module
     * uses.
     */
    protected function configureModels(): void
    {
        Cashier::useCustomerModel(Customer::class);
        Cashier::useSubscriptionModel(Subscription::class);
        Cashier::useSubscriptionItemModel(SubscriptionItem::class);
        Cashier::useTransactionModel(Transaction::class);
    }

    /**
     * Configure the Pennant features that the billing
     * module gates behind plans.
     */
    protected function configureFeatures(): void
    {
        // Register the module's class-based features.
        Pennant::discover('App\\Modules\\Billing\\Features', app_path('Modules/Billing/Features'));

        // Team routes check features against the route team,
        // invitation routes against the invited team, and
        // anything else against the authenticated user.
        Pennant::resolveScopeUsing(function (): Team|User|null {
            /** @var \App\Modules\Core\Identity\Models\User|null $user */
            $user = request()->user();

            return $this->routeTeam(request()) ?? $user;
        });

        // When a gated route is hit past its limit, open the
        // upgrade modal for the team instead of failing.
        EnsureFeaturesAreActive::whenInactive(function (Request $request, array $features): RedirectResponse {
            /** @var \App\Modules\Core\Identity\Models\User|null $user */
            $user = $request->user();

            /** @var string $feature */
            $feature = reset($features);

            // An invitee is not a member of the team, so they cannot
            // upgrade it. Send them back to the invitation with a
            // message instead.
            if (($invitation = $request->route('invitation')) instanceof TeamInvitation) {
                Toast::make(trans('This team has reached its member limit. Ask :inviter to upgrade to Pro to make room for you.', [
                    'inviter' => $invitation->inviter->name,
                ]))->flash();

                return to_route('identity.invitations.show', ['invitation' => $invitation]);
            }

            // Team routes carry the bound team; user routes fall
            // back to the user's current team so there is a team
            // to bill. Without one there is nothing to upgrade.
            if (! ($team = $this->routeTeam($request) ?? $user?->currentTeam) instanceof Team) {
                abort(Response::HTTP_FORBIDDEN);
            }

            return to_route('billing.teams.subscriptions.upgrade', [
                'team' => $team->getRouteKey(),
                'feature' => Feature::from($feature)->value,
            ]);
        });
    }

    /**
     * Get the team a request is about: the bound route team,
     * or the team of a bound invitation.
     */
    protected function routeTeam(Request $request): ?Team
    {
        // If the route has no team, return null.
        if (! ($team = $request->route('team')) instanceof Team) {
            return null;
        }

        // If the route has an invitation, return the invited team.
        // Otherwise, return the route team.
        return ($invitation = $request->route('invitation')) instanceof TeamInvitation ? $invitation->team : $team;
    }
}
