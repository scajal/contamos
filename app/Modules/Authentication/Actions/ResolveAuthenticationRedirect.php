<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Actions;

use Illuminate\Http\Request;

class ResolveAuthenticationRedirect
{
    /**
     * From the given request, resolve the redirect URL
     * to which the user should be sent after authenticating.
     */
    public function fromRequest(Request $request): string
    {
        /** @var \App\Modules\Core\Identity\Models\User $user */
        $user = $request->user();

        // If the user doesn't have a team, redirect to the
        // team creation page.
        if ($user->teams->isEmpty()) {
            return route('identity.teams.create');
        }

        // Otherwise, redirect to the dashboard.
        return route('finance.dashboard.index', ['team' => $user->teams->first()->getKey()]);
    }
}
