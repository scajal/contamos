<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Http\Controllers\User;

use App\Modules\Core\Identity\Actions\User\DeleteUser;
use App\Modules\Core\Identity\Http\Requests\User\Account\DestroyRequest;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Objects\Toast;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use InertiaUI\Modal\Modal;
use Laravel\Paddle\Exceptions\PaddleException;
use Laravel\WorkOS\Http\Requests\AuthKitLogoutRequest;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use WorkOS\Exception\WorkOSException;

class AccountController
{
    /**
     * Display the user's own account.
     */
    public function index(): Response
    {
        // The authenticated user is already shared with every
        // page, so the account page reads it from there.
        return Inertia::render('identity/users/account/index');
    }

    /**
     * Show the confirmation for deleting the account.
     */
    public function delete(#[CurrentUser] User $user): Modal
    {
        return Inertia::modal('identity/users/account/delete', [
            'email' => $user->email,
            // The teams nobody else is on go away with the
            // account, so the modal names them one by one.
            'teams' => $user->teams()
                ->solo()
                ->get()
                ->map(fn (Team $team): array => [
                    'name' => $team->name,
                    'slug' => $team->slug,
                    'subscribed' => $team->subscribed(),
                ])
                ->values(),
        ])->baseRoute('identity.users.account.index');
    }

    /**
     * Remove the user's own account from storage.
     */
    public function destroy(DestroyRequest $request, AuthKitLogoutRequest $logoutRequest, #[CurrentUser] User $user, DeleteUser $deleteUser): HttpResponse
    {
        // Delete the account with the teams the user was alone
        // on. When Paddle or WorkOS refuse, nothing is deleted
        // and the user stays where they were.
        try {
            $deleteUser->using($user);
        } catch (PaddleException $paddleException) {
            report($paddleException);

            // Show a failure message.
            Toast::make(trans('The subscription could not be cancelled. Try again.'))
                ->flash();

            return back();
        } catch (WorkOSException $workOSException) {
            report($workOSException);

            // Show a failure message.
            Toast::make(trans('Your account could not be deleted. Try again.'))
                ->flash();

            return back();
        }

        // The guard still holds the deleted model, and logging out
        // would try to cycle its remember token back into a row
        // that no longer exists, so it is dropped first.
        Auth::forgetUser();

        // The account is gone, so the WorkOS session ends too and
        // the user lands on the marketing home page.
        $response = $logoutRequest->logout('/');

        // Show a success message. Logging out invalidates the
        // session, so the toast is flashed onto the fresh one
        // the user carries to the home page.
        Toast::make(trans('Account deleted'))
            ->flash();

        return $response;
    }
}
