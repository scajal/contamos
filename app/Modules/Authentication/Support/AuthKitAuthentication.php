<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Support;

use App\Modules\Core\Identity\Models\User;
use Carbon\CarbonImmutable;
use Laravel\WorkOS\User as WorkOSUser;

class AuthKitAuthentication
{
    /**
     * Create a user using the provided WorkOS information.
     */
    public function createUsing(WorkOSUser $user): User
    {
        return User::query()->create([
            'avatar' => $user->avatar ?? '',
            'email' => $user->email,
            'email_verified_at' => CarbonImmutable::now(),
            'external_id' => $user->id,
            'is_active' => true,
            'name' => $user->firstName.' '.$user->lastName,
        ]);
    }

    /**
     * Find a user using the provided WorkOS information.
     */
    public function findUsing(WorkOSUser $user): ?User
    {
        return User::query()
            ->where('external_id', $user->id)
            ->orWhere('email', $user->email)
            ->first();
    }

    /**
     * Update a user using the provided WorkOS information.
     */
    public function updateUsing(User $user, WorkOSUser $userFromWorkOS): User
    {
        return tap($user)->update(array_filter([
            'avatar' => $userFromWorkOS->avatar ?? '',
            'external_id' => $user->external_id ?? $userFromWorkOS->id,
        ]));
    }
}
