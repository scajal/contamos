<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Http\Controllers\User;

use App\Modules\Authentication\Support\AuthKitAuthorizedApplications;
use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Identity\Actions\User\RevokeAssistant;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Mcp\Http\Middleware\AuthenticateMcpToken;
use App\Modules\Support\Objects\Toast;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AssistantController
{
    /**
     * Display the AI assistants connected to the user's account,
     * and how to connect one.
     */
    public function index(#[CurrentUser] User $user, AuthKitAuthorizedApplications $authorizedApplications): Response
    {
        // The plan that decides whether assistants may work is the
        // current team's; a user on no team has none to upgrade.
        $team = isset($user->current_team_id) ? $user->currentTeam : null;

        // Assistants work on every plan today; the page only
        // offers the upgrade when a plan leaves them out.
        $isLocked = $team instanceof Team && ! Feature::UseMcp->enabled($team);

        return Inertia::render('identity/users/assistants/index', [
            'connected_apps' => Inertia::defer(fn (): array => $this->connectedApps($user, $authorizedApplications)),
            'is_locked' => $isLocked,
            'server_url' => AuthenticateMcpToken::resource(),
        ]);
    }

    /**
     * Revoke an AI assistant's access to the user's account.
     */
    public function destroy(string $application, #[CurrentUser] User $user, AuthKitAuthorizedApplications $authorizedApplications, RevokeAssistant $revokeAssistant): RedirectResponse
    {
        // A user WorkOS does not know has nothing connected.
        abort_if($user->external_id === null, HttpResponse::HTTP_NOT_FOUND);

        try {
            // Only an application the user authorized may be
            // revoked, so it is looked up among theirs first.
            $authorized = collect($authorizedApplications->listFor($user->external_id))
                ->firstWhere('id', $application);

            abort_if($authorized === null, HttpResponse::HTTP_NOT_FOUND);

            // Revoke it at WorkOS and refuse its tokens at once.
            $revokeAssistant
                ->for($user)
                ->using($authorized);
        } catch (RequestException|ConnectionException $exception) {
            report($exception);

            // Show a failure message. When the lookup failed nothing
            // changed; when WorkOS refused the revocation, the
            // assistant is already refused here.
            Toast::make(isset($authorized)
                ? trans('The assistant can no longer access your account, but it could not be removed from the list. Try again.')
                : trans('The assistant could not be disconnected. Try again.'))
                ->flash();

            return back();
        }

        // Show a success message.
        Toast::make(trans('Assistant disconnected'))
            ->flash();

        return back();
    }

    /**
     * Get the applications the user authorized at WorkOS. When
     * WorkOS cannot answer, the page says so instead of failing.
     *
     * @return array{items: array<int, array{id: string, name: string}>, failed: bool}
     */
    protected function connectedApps(User $user, AuthKitAuthorizedApplications $authorizedApplications): array
    {
        // A user WorkOS does not know has authorized nothing.
        if ($user->external_id === null) {
            return ['items' => [], 'failed' => false];
        }

        try {
            $applications = $authorizedApplications->listFor($user->external_id);
        } catch (RequestException|ConnectionException $exception) {
            report($exception);

            return ['items' => [], 'failed' => true];
        }

        return [
            'items' => array_map(fn (array $application): array => [
                'id' => $application['id'],
                'name' => $application['name'],
            ], $applications),
            'failed' => false,
        ];
    }
}
