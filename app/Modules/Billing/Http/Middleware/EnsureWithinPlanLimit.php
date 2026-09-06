<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Middleware;

use App\Modules\Core\Identity\Models\Team;
use App\Modules\Support\Objects\Toast;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWithinPlanLimit
{
    /**
     * Create an instance of the middleware with
     * the given feature.
     */
    public static function for(string $feature): string
    {
        return self::class.':'.$feature;
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        // Find the team.
        /** @var \App\Modules\Core\Identity\Models\Team|null $team */
        $team = $request->route('team');

        // If the team is within the plan limit, let the request through.
        if (! $team instanceof Team || $team->withinPlanLimit($feature)) {
            return $next($request);
        }

        // Flash an upsell message and redirect the team to
        // the billing page instead of letting the write through.
        Toast::make(trans('Upgrade to Pro to add more :feature.', ['feature' => $feature]))
            ->flash();

        return redirect()->route('billing.teams.subscriptions.index', ['team' => $team->getRouteKey()]);
    }
}
