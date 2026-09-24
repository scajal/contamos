<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

/**
 * The applications, such as AI assistants, a user authorized
 * through AuthKit to act on their account. The WorkOS SDK has
 * no wrapper for these endpoints, so they are called directly.
 */
class AuthKitAuthorizedApplications
{
    /**
     * The WorkOS API every request goes to.
     */
    protected const string BASE_URL = 'https://api.workos.com';

    /**
     * List the applications the WorkOS user authorized, newest
     * first as WorkOS returns them, reading every page.
     *
     * @return array<int, array{id: string, name: string, client_id: string|null}>
     *
     * @throws \Illuminate\Http\Client\RequestException When WorkOS answers with an error.
     * @throws \Illuminate\Http\Client\ConnectionException When WorkOS cannot be reached.
     */
    public function listFor(string $identifier): array
    {
        $applications = [];
        $after = null;

        do {
            $response = $this->request()
                ->get("user_management/users/{$identifier}/authorized_applications", array_filter([
                    'limit' => 100,
                    'after' => $after,
                ]))
                ->throw();

            // Each entry carries the application it authorized; an
            // entry without an identifier cannot be revoked, so it
            // is left out.
            foreach (is_array($data = $response->json('data')) ? $data : [] as $entry) {
                if (is_array($entry) && ($application = $this->application($entry)) !== null) {
                    $applications[] = $application;
                }
            }

            // WorkOS names the cursor of the next page while
            // there is one.
            $after = $response->json('list_metadata.after');
        } while (is_string($after) && $after !== '');

        return $applications;
    }

    /**
     * Revoke the application's authorization on the WorkOS
     * user. One WorkOS no longer holds is already revoked,
     * which is what the caller asked for.
     *
     * @throws \Illuminate\Http\Client\RequestException When WorkOS answers with an error.
     * @throws \Illuminate\Http\Client\ConnectionException When WorkOS cannot be reached.
     */
    public function revokeFor(string $identifier, string $applicationId): void
    {
        $response = $this->request()
            ->delete("user_management/users/{$identifier}/authorized_applications/{$applicationId}");

        if ($response->status() === Response::HTTP_NOT_FOUND) {
            return;
        }

        $response->throw();
    }

    /**
     * Shape one authorized application entry from the
     * application it carries. The entry's own identifier names
     * the authorization, which revoking does not take, and
     * WorkOS does not say when access was granted.
     *
     * @param  array<array-key, mixed>  $entry
     * @return array{id: string, name: string, client_id: string|null}|null
     */
    protected function application(array $entry): ?array
    {
        if (! is_array($application = $entry['application'] ?? null)) {
            return null;
        }

        // The application's identifier is what revoking names.
        if (! is_string($id = $application['id'] ?? null) || $id === '') {
            return null;
        }

        $name = $application['name'] ?? null;
        $clientId = $application['client_id'] ?? null;

        return [
            'id' => $id,
            'name' => is_string($name) && $name !== '' ? $name : $id,
            'client_id' => is_string($clientId) && $clientId !== '' ? $clientId : null,
        ];
    }

    /**
     * Start a request to the WorkOS API with the account's
     * secret key.
     */
    protected function request(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->withToken(config()->string('services.workos.secret'))
            ->acceptJson()
            ->timeout(10);
    }
}
