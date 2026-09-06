<?php

declare(strict_types=1);

use App\Modules\Authentication\Actions\ResolveAuthenticationRedirect;
use App\Modules\Core\Identity\Models\User;
use Illuminate\Http\Request;

/**
 * Unit tests for \App\Modules\Authentication\Actions\ResolveAuthenticationRedirect.
 */
it('resolves team creation when the user has no teams', function (): void {
    $user = User::factory()->create();
    $request = Request::create('/');
    $request->setUserResolver(fn (): User => $user);

    expect((new ResolveAuthenticationRedirect)->fromRequest($request))
        ->toBe(route('identity.teams.create'));
});

it('resolves the dashboard of the first team when the user has teams', function (): void {
    [$user, $team] = memberWithTeam();
    $request = Request::create('/');
    $request->setUserResolver(fn (): User => $user);

    expect((new ResolveAuthenticationRedirect)->fromRequest($request))
        ->toBe(route('finance.teams.dashboard.index', ['team' => $team->getRouteKey()]));
});

it('resolves the intended url stored by the guest redirect and forgets it', function (): void {
    [$user, $team] = memberWithTeam();
    $request = Request::create('/');
    $request->setUserResolver(fn (): User => $user);
    $request->setLaravelSession(session()->driver());
    $request->session()->put('url.intended', 'https://app.test/invitations/abc');

    expect((new ResolveAuthenticationRedirect)->fromRequest($request))
        ->toBe('https://app.test/invitations/abc')
        ->and($request->session()->has('url.intended'))->toBeFalse();
});

it('ignores an empty intended url', function (): void {
    [$user, $team] = memberWithTeam();
    $request = Request::create('/');
    $request->setUserResolver(fn (): User => $user);
    $request->setLaravelSession(session()->driver());
    $request->session()->put('url.intended', '');

    expect((new ResolveAuthenticationRedirect)->fromRequest($request))
        ->toBe(route('finance.teams.dashboard.index', ['team' => $team->getRouteKey()]));
});
