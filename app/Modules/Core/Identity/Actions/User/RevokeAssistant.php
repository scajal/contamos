<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Actions\User;

use App\Modules\Authentication\Support\AuthKitAuthorizedApplications;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Mcp\Models\RevokedGrant;
use Exception;

class RevokeAssistant
{
    /**
     * The user whose assistant is revoked.
     */
    protected ?User $user = null;

    /**
     * Create a new instance of the class.
     */
    public function __construct(
        protected AuthKitAuthorizedApplications $authorizedApplications
    ) {}

    /**
     * Set the user whose assistant is revoked.
     */
    public function for(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Stop the tokens an application the user authorized already
     * holds at once instead of when they expire, then revoke it
     * at WorkOS. The tokens stay refused when WorkOS fails, so
     * the assistant is cut off either way and revoking again
     * only retries WorkOS.
     *
     * @param  array{id: string, name: string, client_id: string|null}  $application
     *
     * @throws \Illuminate\Http\Client\RequestException When WorkOS refuses the revocation.
     * @throws \Illuminate\Http\Client\ConnectionException When WorkOS cannot be reached.
     */
    public function using(array $application): void
    {
        if (! ($user = $this->user) instanceof User || ($identifier = $user->external_id) === null) {
            throw new Exception('An assistant can only be revoked for a user known to WorkOS.');
        }

        // WorkOS names no session per application, so every
        // token issued to the client before now is refused.
        // An application without a client identifier goes by
        // its own identifier.
        RevokedGrant::query()
            ->make([
                'client_id' => $application['client_id'] ?? $application['id'],
                'session_id' => null,
            ])
            ->user()
            ->associate($user)
            ->save();

        // Then the authorization goes at WorkOS.
        $this->authorizedApplications->revokeFor($identifier, $application['id']);
    }
}
