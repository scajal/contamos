<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Filament\Facades\Filament;

/**
 * Feature tests for \App\Modules\Administration\Providers\PanelProvider.
 *
 * Reaching either panel is an allowlist check on the address, run by
 * Filament's own Authenticate middleware. These tests pin that the
 * check is wired, so a member of a team cannot read another team's
 * books by walking into the panel.
 */
it('redirects guests to login', function (): void {
    $team = Team::factory()->create();

    $this->get(route('filament.administration.pages.dashboard'))
        ->assertRedirectToRoute('login');

    $this->get(route('filament.team.pages.dashboard', ['tenant' => $team->getRouteKey()]))
        ->assertRedirectToRoute('login');
});

it('denies the administration panel to a user outside the allowlist', function (): void {
    [$user] = actingMember();

    expect($user->email)->not->toBe('operator@example.com');

    $this->get(route('filament.administration.pages.dashboard'))
        ->assertForbidden();
});

it('denies the team panel to a user outside the allowlist', function (): void {
    [, $team] = actingMember();

    $this->get(route('filament.team.pages.dashboard', ['tenant' => $team->getRouteKey()]))
        ->assertForbidden();
});

it('allows an operator into the administration panel', function (): void {
    actingOperator();

    $this->get(route('filament.administration.pages.dashboard'))
        ->assertOk();
});

it('allows an operator into the panel of a team they hold no membership in', function (): void {
    [$operator] = actingOperator();
    $foreign = Team::factory()->create();

    expect($operator->belongsToTeam($foreign))->toBeFalse();

    $this->get(route('filament.team.pages.dashboard', ['tenant' => $foreign->getRouteKey()]))
        ->assertOk();
});

it('offers every team as a tenant of the team panel', function (): void {
    [$operator] = actingOperator();
    $foreign = Team::factory()->create();

    $tenants = $operator->getTenants(Filament::getPanel('team'));

    expect($tenants->pluck('id'))->toContain($foreign->getKey());
});

it('lets an operator reach a tenant they hold no membership in', function (): void {
    [$operator] = actingOperator();
    $foreign = Team::factory()->create();

    expect($operator->canAccessTenant($foreign))->toBeTrue();
});

it('keeps a user outside the allowlist away from every tenant', function (): void {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    expect($user->canAccessTenant($team))->toBeFalse();
});
