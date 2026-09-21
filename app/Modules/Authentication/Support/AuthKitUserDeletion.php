<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Support;

use Laravel\WorkOS\WorkOS;
use WorkOS\Exception\NotFoundException;
use WorkOS\UserManagement;

class AuthKitUserDeletion
{
    /**
     * Delete the user WorkOS knows under the given identifier,
     * so signing in again cannot silently recreate the account
     * from the provider.
     *
     * @throws \WorkOS\Exception\WorkOSException When WorkOS refuses the deletion.
     */
    public function deleteUsing(string $identifier): void
    {
        // Nothing else in the request cycle sets the SDK's
        // credentials, so they are set here.
        WorkOS::configure();

        // A user WorkOS no longer holds is already deleted, which
        // is what the caller asked for, so a retry after a partial
        // failure still succeeds.
        try {
            new UserManagement()->deleteUser($identifier);
        } catch (NotFoundException) {
            // Nothing left to delete at WorkOS.
        }
    }
}
