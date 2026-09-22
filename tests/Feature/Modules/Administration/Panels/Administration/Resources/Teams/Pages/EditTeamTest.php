<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Administration\Resources\Teams\Pages\EditTeam;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Administration\Resources\Teams\Pages\EditTeam.
 */
it('updates the team name and slug', function (): void {
    actingOperator();
    $team = Team::factory()->create(['name' => 'Old', 'slug' => 'old']);

    Livewire::test(EditTeam::class, ['record' => $team->getRouteKey()])
        ->fillForm([
            'name' => 'Los Pinos',
            'slug' => 'los-pinos',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect($team->fresh()?->name)->toBe('Los Pinos')
        ->and($team->fresh()?->slug)->toBe('los-pinos');
});

it('grants a plan by hand with an expiry', function (): void {
    actingOperator();
    $team = Team::factory()->create();
    $until = CarbonImmutable::now()->addMonth()->startOfMinute();

    Livewire::test(EditTeam::class, ['record' => $team->getRouteKey()])
        ->fillForm([
            'plan_override' => Plan::Pro,
            'plan_override_expires_at' => $until,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect($team->fresh()?->plan_override)->toBe(Plan::Pro)
        ->and($team->fresh()?->plan_override_expires_at?->equalTo($until))->toBeTrue()
        ->and($team->fresh()?->plan())->toBe(Plan::Pro);
});

it('clears a granted plan', function (): void {
    actingOperator();
    $team = Team::factory()->grantedPlan(Plan::Pro)->create();

    Livewire::test(EditTeam::class, ['record' => $team->getRouteKey()])
        ->fillForm(['plan_override' => null, 'plan_override_expires_at' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($team->fresh()?->plan_override)->toBeNull()
        ->and($team->fresh()?->plan())->toBe(Plan::Free);
});

it('shows the current plan and the granted plan when editing', function (): void {
    actingOperator();
    $team = Team::factory()->grantedPlan(Plan::Pro)->create();

    Livewire::test(EditTeam::class, ['record' => $team->getRouteKey()])
        ->assertFormFieldExists('plan_override')
        ->assertFormFieldExists('plan_override_expires_at')
        ->assertSee(Plan::Pro->getLabel());
});
