<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Http\Controllers\Team;

use App\Modules\Core\Identity\Actions\Team\CreateInvitation;
use App\Modules\Core\Identity\Actions\Team\Invitation\RenewAndResendTeamInvitation;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Http\Requests\Team\Invitation\IndexRequest;
use App\Modules\Core\Identity\Http\Requests\Team\Invitation\StoreRequest;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\Team\TeamInvitation;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Objects\Toast;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;
use InertiaUI\Modal\Modal;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class InvitationController
{
    /**
     * The invitation emails a member may send per day, new
     * invitations and resends together, so one account cannot
     * eat the daily sending quota of the mail provider.
     */
    protected const int EMAILS_PER_DAY = 5;

    /**
     * Display a listing of the resource.
     */
    public function index(Team $team, IndexRequest $request): Response
    {
        return Inertia::render('identity/teams/invitations/index', [
            'filters' => $request->validated('filter', []),
            'invitations' => Inertia::defer(
                fn (): LengthAwarePaginator => QueryBuilder::for($team->invitations()->with('inviter'), $request)
                    ->allowedFilters(...$request->filters())
                    ->latest()
                    ->paginate(10)
                    ->withQueryString()
                    // The badge names the access the invitee will get,
                    // which is the role as the team's plan applies it.
                    ->through(fn (TeamInvitation $invitation): TeamInvitation => tap(
                        $invitation,
                        fn (TeamInvitation $invitation): mixed => $invitation->setAttribute('role', $team->effectiveRole($invitation->role)),
                    )),
            ),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Team $team): Modal
    {
        return Inertia::modal('identity/teams/invitations/create', [
            // Choosing a role is a pro feature, so a free team gets
            // no options and a note pointing at pro instead.
            'roles' => $team->assignsRoles() ? Role::options($team->assignableRoles()) : [],
        ])->baseRoute('identity.teams.invitations.index', ['team' => $team->getRouteKey()]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Team $team, StoreRequest $request, #[CurrentUser] User $user, CreateInvitation $createInvitation): RedirectResponse
    {
        // A member who used up the day's emails has to wait.
        abort_if($this->hasUsedUpEmails($user), HttpResponse::HTTP_TOO_MANY_REQUESTS);

        // Create the invitation and email it to the invitee.
        $createInvitation
            ->for($team)
            ->by($user)
            ->using($request->safe(['email', 'role']));

        // Count the email against the member's daily quota.
        $this->countEmail($user);

        // Show a success message.
        Toast::make(trans('Invitation sent'))
            ->flash();

        return back();
    }

    /**
     * Renew the specified resource and email it again.
     */
    public function resend(Team $team, TeamInvitation $invitation, #[CurrentUser] User $user, RenewAndResendTeamInvitation $renewAndResendTeamInvitation): RedirectResponse
    {
        // An accepted invitation cannot be sent again.
        abort_if($invitation->isAccepted(), HttpResponse::HTTP_CONFLICT);

        // An invitation that just went out is not sent again,
        // so a slow inbox does not turn into a burst of emails.
        if ($invitation->wasSentRecently()) {
            Toast::make(trans('This invitation was sent less than an hour ago'))
                ->flash();

            return back();
        }

        // A member who used up the day's emails has to wait.
        abort_if($this->hasUsedUpEmails($user), HttpResponse::HTTP_TOO_MANY_REQUESTS);

        // Renew and re-send the invitation.
        $renewAndResendTeamInvitation->using($invitation);

        // Count the email against the member's daily quota.
        $this->countEmail($user);

        // Show a success message.
        Toast::make(trans('Invitation sent'))
            ->flash();

        return back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Team $team, TeamInvitation $invitation): RedirectResponse
    {
        // An accepted invitation is kept as history.
        abort_if($invitation->isAccepted(), HttpResponse::HTTP_CONFLICT);

        // Delete the invitation.
        $invitation->delete();

        // Show a success message.
        Toast::make(trans('Deleted'))
            ->flash();

        return back();
    }

    /**
     * Indicate if the member has sent as many invitation
     * emails as the day allows.
     */
    protected function hasUsedUpEmails(User $user): bool
    {
        return RateLimiter::tooManyAttempts($this->emailQuotaKey($user), self::EMAILS_PER_DAY);
    }

    /**
     * Count one invitation email against the member's daily
     * quota. The count is kept for a day from the first email.
     */
    protected function countEmail(User $user): void
    {
        RateLimiter::hit($this->emailQuotaKey($user), CarbonImmutable::now()->addDay());
    }

    /**
     * Get the rate limiter key under which the member's
     * invitation emails are counted.
     */
    protected function emailQuotaKey(User $user): string
    {
        return 'invitation-emails:'.$user->getKey();
    }
}
