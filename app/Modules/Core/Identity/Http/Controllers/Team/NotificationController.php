<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Http\Controllers\Team;

use App\Modules\Core\Identity\Enums\Team\NotificationType;
use App\Modules\Core\Identity\Http\Requests\Team\Notification\UpdateRequest;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Objects\Toast;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController
{
    /**
     * Show the signed-in member's notification switches for
     * the team.
     */
    public function edit(Team $team, #[CurrentUser] User $user): Response
    {
        $membership = $user->membershipOn($team);

        return Inertia::render('identity/teams/notifications/edit', [
            'notifications' => array_map(fn (NotificationType $type): array => [
                'description' => $type->description(),
                'enabled' => $membership?->wantsNotification($type) ?? true,
                'label' => $type->getLabel(),
                'value' => $type->value,
            ], NotificationType::cases()),
            'team' => $team,
        ]);
    }

    /**
     * Store the signed-in member's notification switches for
     * the team.
     */
    public function update(Team $team, UpdateRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        // Store every switch on the membership, which the
        // activity log does not follow.
        $team->users()->updateExistingPivot($user->getKey(), [
            'notification_preferences' => array_map(fn (mixed $enabled): bool => (bool) $enabled, $request->validated()),
        ]);

        // Show a success message.
        Toast::make(trans('Saved'))
            ->flash();

        return back();
    }
}
