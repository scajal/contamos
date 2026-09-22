<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\RecurringRules\Pages\ListRecurringRules;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Core\Identity\Models\Team;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Team\Resources\RecurringRules\Pages\ListRecurringRules.
 */
it('lists the recurring rules of the current tenant', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $rule = RecurringRule::factory()->for(Account::factory()->for($team))->create();

    Livewire::test(ListRecurringRules::class)
        ->loadTable()
        ->assertOk()
        ->assertCanSeeTableRecords([$rule]);
});

it('hides the recurring rules of another team', function (): void {
    [, $team] = actingOperator();
    $mine = RecurringRule::factory()->for(Account::factory()->for($team))->create();
    $theirs = RecurringRule::factory()->for(Account::factory()->for(Team::factory()->create()))->create();

    onTeamPanel($team);

    Livewire::test(ListRecurringRules::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});
