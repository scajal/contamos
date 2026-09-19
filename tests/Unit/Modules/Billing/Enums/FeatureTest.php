<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Feature;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Core\Finance\Models\Team\Account;
use Laravel\Pennant\Feature as Pennant;

/**
 * Unit tests for \App\Modules\Billing\Enums\Feature.
 */
it('reads the limit from the Pennant feature class', function (): void {
    expect(Feature::AddAccounts->limitOn(Plan::Free))->toBe(3)
        ->and(Feature::AddAccounts->limitOn(Plan::Pro))->toBeNull();
});

it('tells a switch feature from a quota feature', function (): void {
    expect(Feature::AssignRoles->isSwitch())->toBeTrue()
        ->and(Feature::AddAccounts->isSwitch())->toBeFalse()
        ->and(Feature::AddMembers->isSwitch())->toBeFalse()
        ->and(Feature::AddTeams->isSwitch())->toBeFalse();
});

it('has no limit for a switch feature', function (): void {
    expect(Feature::AssignRoles->limitOn(Plan::Free))->toBeNull()
        ->and(Feature::AssignRoles->limitOn(Plan::Pro))->toBeNull();
});

it('reads whether the plan includes the feature', function (): void {
    expect(Feature::AssignRoles->includedIn(Plan::Free))->toBeFalse()
        ->and(Feature::AssignRoles->includedIn(Plan::Pro))->toBeTrue()
        ->and(Feature::AddAccounts->includedIn(Plan::Free))->toBeTrue()
        ->and(Feature::AddAccounts->includedIn(Plan::Pro))->toBeTrue();
});

it('lists every feature with its limit or inclusion on the plan', function (): void {
    expect(Plan::Free->features())->toBe([
        'accounts:create' => 3,
        'members:create' => 2,
        'teams:create' => 1,
        'members:roles' => false,
    ])->and(Plan::Pro->features())->toBe([
        'accounts:create' => null,
        'members:create' => null,
        'teams:create' => null,
        'members:roles' => true,
    ]);
});

it('registers every case as a Pennant feature', function (): void {
    foreach (Feature::cases() as $feature) {
        expect(Pennant::defined())->toContain($feature->value);
    }
});

it('checks the feature through Pennant for the scope', function (): void {
    [$user, $team] = memberWithTeam();
    Account::factory()->count(3)->active()->for($team)->create();

    expect(Feature::AddAccounts->enabled($team))->toBeFalse();

    Pennant::for($team)->activate(Feature::AddAccounts);

    expect(Feature::AddAccounts->enabled($team))->toBeTrue();
});

it('rejects a scope of the wrong kind', function (): void {
    [$user, $team] = memberWithTeam();

    expect(fn (): bool => Feature::AddTeams->enabled($team))->toThrow(TypeError::class)
        ->and(fn (): bool => Feature::AddAccounts->enabled($user))->toThrow(TypeError::class);
});
