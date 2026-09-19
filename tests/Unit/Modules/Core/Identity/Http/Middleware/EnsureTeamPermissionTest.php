<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Enums\Team\Permission;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Http\Middleware\EnsureTeamPermission;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Unit tests for \App\Modules\Core\Identity\Http\Middleware\EnsureTeamPermission
 * branches that HTTP routing does not reach.
 */

/**
 * Build a request bound to the given team and user, the way the
 * team route group hands it to the middleware.
 */
function permissionRequest(mixed $team, ?User $user): Request
{
    $request = Request::create('/teams/team', 'GET');
    $request->setUserResolver(fn (): ?User => $user);
    $request->setRouteResolver(function () use ($request, $team): \Illuminate\Routing\Route {
        $route = new \Illuminate\Routing\Route('GET', 'teams/{team}', fn (): string => 'ok');
        $route->bind($request);
        $route->setParameter('team', $team);

        return $route;
    });

    return $request;
}

it('names the permission the way pennant names a feature', function (): void {
    expect(EnsureTeamPermission::using(Permission::DeleteTeam))
        ->toBe(EnsureTeamPermission::class.':'.Permission::DeleteTeam->value);
});

it('passes a user whose role grants the permission', function (): void {
    [$user, $team] = memberWithTeam();

    $response = new EnsureTeamPermission()->handle(
        permissionRequest($team, $user),
        fn (): Response => new Response('ok'),
        Permission::DeleteTeam->value,
    );

    expect($response->getContent())->toBe('ok');
});

it('returns 404 when the team is not bound', function (): void {
    [$user] = memberWithTeam();

    try {
        new EnsureTeamPermission()->handle(
            permissionRequest('team', $user),
            fn (): Response => new Response('ok'),
            Permission::DeleteTeam->value,
        );
        $this->fail('Expected a not found exception.');
    } catch (NotFoundHttpException|HttpException $exception) {
        expect($exception->getStatusCode())->toBe(Response::HTTP_NOT_FOUND);
    }
});

it('returns 404 when nobody is authenticated', function (): void {
    $team = Team::factory()->create();

    try {
        new EnsureTeamPermission()->handle(
            permissionRequest($team, null),
            fn (): Response => new Response('ok'),
            Permission::DeleteTeam->value,
        );
        $this->fail('Expected a not found exception.');
    } catch (NotFoundHttpException|HttpException $exception) {
        expect($exception->getStatusCode())->toBe(Response::HTTP_NOT_FOUND);
    }
});

it('returns 403 when the role of the user does not grant the permission', function (): void {
    [$user, $team] = memberWithTeam(Role::Member);

    try {
        new EnsureTeamPermission()->handle(
            permissionRequest($team, $user),
            fn (): Response => new Response('ok'),
            Permission::DeleteTeam->value,
        );
        $this->fail('Expected a forbidden exception.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
    }
});
