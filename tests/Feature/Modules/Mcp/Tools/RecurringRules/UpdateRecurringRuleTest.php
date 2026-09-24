<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\RecurringRule\Frequency;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\RecurringRules\UpdateRecurringRule;
use Carbon\CarbonImmutable;
use Laravel\Pennant\Feature as Pennant;

/**
 * Feature tests for \App\Modules\Mcp\Tools\RecurringRules\UpdateRecurringRule.
 */
beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-05-20 10:00:00'));

    [$this->user, $this->team] = mcpMember();

    $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
    $account = Account::factory()->bank()->active()->for($this->team)->create();
    $account->currencies()->attach($currency, ['balance' => '0']);

    $this->rule = RecurringRule::factory()->expense()->failed()->create([
        'account_id' => $account->getKey(),
        'amount' => '25.00',
        'currency_id' => $currency->getKey(),
        'description' => 'Rent',
        'frequency' => Frequency::Monthly,
        'last_generated_due_date' => '2026-05-15',
        'starts_on' => '2026-01-15',
        'team_id' => $this->team->getKey(),
    ]);
});

it('is destructive', function (): void {
    expect(new UpdateRecurringRule()->annotations())->toMatchArray(['destructiveHint' => true]);
});

it('changes only what the call names, from the next occurrence on', function (): void {
    ContamosServer::actingAs($this->user)
        ->tool(UpdateRecurringRule::class, [
            'team' => $this->team->slug,
            'rule' => $this->rule->getKey(),
            'amount' => '30.00',
        ])
        ->assertOk();

    $this->rule->refresh();

    expect($this->rule->amount)->toBe('30.000000000000000000')
        ->and($this->rule->description)->toBe('Rent')
        ->and($this->rule->starts_on->toDateString())->toBe('2026-01-15')
        ->and($this->rule->failure_reason)->toBeNull()
        ->and($this->rule->next_due_date?->toDateString())->toBe('2026-06-15');
});

it('refuses a team whose plan lacks recurring transactions', function (): void {
    Pennant::for($this->team)->deactivate(Feature::RecurTransactions);

    ContamosServer::actingAs($this->user)
        ->tool(UpdateRecurringRule::class, [
            'team' => $this->team->slug,
            'rule' => $this->rule->getKey(),
            'amount' => '30.00',
        ])
        ->assertHasErrors(["This team's plan does not allow this"]);

    expect($this->rule->refresh()->amount)->toBe('25.000000000000000000');
});

it('does not find another team\'s rule', function (): void {
    $foreign = RecurringRule::factory()->expense()->create();

    ContamosServer::actingAs($this->user)
        ->tool(UpdateRecurringRule::class, [
            'team' => $this->team->slug,
            'rule' => $foreign->getKey(),
            'amount' => '30.00',
        ])
        ->assertHasErrors(['Recurring rule not found.']);
});
