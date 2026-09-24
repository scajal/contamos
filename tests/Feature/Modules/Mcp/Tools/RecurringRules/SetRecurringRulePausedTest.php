<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\RecurringRule\Frequency;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\RecurringRules\SetRecurringRulePaused;
use Carbon\CarbonImmutable;
use Laravel\Pennant\Feature as Pennant;

/**
 * Feature tests for \App\Modules\Mcp\Tools\RecurringRules\SetRecurringRulePaused.
 */
beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-05-20 10:00:00'));

    [$this->user, $this->team] = mcpMember();

    $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
    $account = Account::factory()->bank()->active()->for($this->team)->create();
    $account->currencies()->attach($currency, ['balance' => '0']);

    $this->rule = RecurringRule::factory()->expense()->create([
        'account_id' => $account->getKey(),
        'currency_id' => $currency->getKey(),
        'frequency' => Frequency::Monthly,
        'last_generated_due_date' => '2026-02-15',
        'starts_on' => '2026-01-15',
        'team_id' => $this->team->getKey(),
    ]);
});

it('is destructive', function (): void {
    expect(new SetRecurringRulePaused()->annotations())->toMatchArray(['destructiveHint' => true]);
});

it('pauses a rule', function (): void {
    ContamosServer::actingAs($this->user)
        ->tool(SetRecurringRulePaused::class, ['team' => $this->team->slug, 'rule' => $this->rule->getKey(), 'paused' => true])
        ->assertOk()
        ->assertSee('paused');

    expect($this->rule->refresh()->is_active)->toBeFalse();
});

it('resumes from the next due date without backfilling the paused stretch', function (): void {
    $this->rule->deactivate();

    ContamosServer::actingAs($this->user)
        ->tool(SetRecurringRulePaused::class, ['team' => $this->team->slug, 'rule' => $this->rule->getKey(), 'paused' => false])
        ->assertOk();

    $this->rule->refresh();

    expect($this->rule->is_active)->toBeTrue()
        ->and($this->rule->last_generated_due_date?->toDateString())->toBe('2026-05-15')
        ->and($this->rule->next_due_date?->toDateString())->toBe('2026-06-15');
});

it('refuses a team whose plan lacks recurring transactions', function (): void {
    Pennant::for($this->team)->deactivate(Feature::RecurTransactions);

    ContamosServer::actingAs($this->user)
        ->tool(SetRecurringRulePaused::class, ['team' => $this->team->slug, 'rule' => $this->rule->getKey(), 'paused' => true])
        ->assertHasErrors(["This team's plan does not allow this"]);

    expect($this->rule->refresh()->is_active)->toBeTrue();
});

it('does not find another team\'s rule', function (): void {
    $foreign = RecurringRule::factory()->expense()->create();

    ContamosServer::actingAs($this->user)
        ->tool(SetRecurringRulePaused::class, ['team' => $this->team->slug, 'rule' => $foreign->getKey(), 'paused' => true])
        ->assertHasErrors(['Recurring rule not found.']);

    expect($foreign->refresh()->is_active)->toBeTrue();
});
