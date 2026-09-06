<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Http\Middleware\EnsureTeamMembership;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Unit tests for \App\Modules\Core\Identity\Http\Middleware\EnsureTeamMembership
 * branches that HTTP routing does not reach.
 */
it('resolves a string team route parameter by route key', function (): void {
    [$user, $team] = memberWithTeam();
    $teamKey = $team->getRouteKey();

    if (! is_string($teamKey)) {
        throw new InvalidArgumentException('Expected a string team key.');
    }

    $request = Request::create('/teams/'.$teamKey, 'GET');
    $request->setUserResolver(fn (): User => $user);
    $request->setRouteResolver(function () use ($request, $teamKey): \Illuminate\Routing\Route {
        $route = new \Illuminate\Routing\Route('GET', 'teams/{team}', fn (): string => 'ok');
        $route->bind($request);
        $route->setParameter('team', $teamKey);

        return $route;
    });

    $response = (new EnsureTeamMembership)->handle($request, fn (): Response => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('returns 404 when the string team route parameter does not match a team', function (): void {
    $user = User::factory()->create();

    $request = Request::create('/teams/missing', 'GET');
    $request->setUserResolver(fn (): User => $user);
    $request->setRouteResolver(function () use ($request): \Illuminate\Routing\Route {
        $route = new \Illuminate\Routing\Route('GET', 'teams/{team}', fn (): string => 'ok');
        $route->bind($request);
        $route->setParameter('team', 'missing');

        return $route;
    });

    try {
        (new EnsureTeamMembership)->handle($request, fn (): Response => new Response('ok'));
        $this->fail('Expected a not found exception.');
    } catch (NotFoundHttpException|HttpException $exception) {
        expect($exception->getStatusCode())->toBe(Response::HTTP_NOT_FOUND);
    }
});

it('returns 404 when the request has no authenticated user', function (): void {
    $team = Team::factory()->create();

    $request = Request::create('/teams/'.$team->getKey(), 'GET');
    $request->setUserResolver(fn (): null => null);
    $request->setRouteResolver(function () use ($request, $team): \Illuminate\Routing\Route {
        $route = new \Illuminate\Routing\Route('GET', 'teams/{team}', fn (): string => 'ok');
        $route->bind($request);
        $route->setParameter('team', $team);

        return $route;
    });

    try {
        (new EnsureTeamMembership)->handle($request, fn (): Response => new Response('ok'));
        $this->fail('Expected a not found exception.');
    } catch (NotFoundHttpException|HttpException $exception) {
        expect($exception->getStatusCode())->toBe(Response::HTTP_NOT_FOUND);
    }
});
