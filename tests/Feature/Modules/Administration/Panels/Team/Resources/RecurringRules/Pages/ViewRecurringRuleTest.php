<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\RecurringRules\Pages\ViewRecurringRule;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Team\Resources\RecurringRules\Pages\ViewRecurringRule.
 */
it('renders the recurring rule with its timestamps', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $createdAt = CarbonImmutable::parse('2026-03-14 09:30:00');
    $rule = RecurringRule::factory()->for(Account::factory()->for($team))->create([
        'description' => 'Alquiler mensual',
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);

    Livewire::test(ViewRecurringRule::class, ['record' => $rule->getKey()])
        ->assertOk()
        ->assertSee('Alquiler mensual')
        ->assertSchemaStateSet([
            'created_at' => $rule->created_at->toDateTimeString(),
            'updated_at' => $rule->updated_at->toDateTimeString(),
        ])
        ->assertFormFieldDisabled('created_at')
        ->assertFormFieldDisabled('updated_at');
});
