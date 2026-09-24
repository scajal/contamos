<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\RecurringRule\Frequency;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\RecurringRules\CreateRecurringRule;
use Carbon\CarbonImmutable;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Pennant\Feature as Pennant;

/**
 * Feature tests for \App\Modules\Mcp\Tools\RecurringRules\CreateRecurringRule.
 */
beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-05-20 10:00:00'));

    [$this->user, $this->team] = mcpMember();

    $this->currency = Currency::factory()->active()->fiat()->create(['code' => 'UYU', 'decimal_places' => 2]);
    $this->account = Account::factory()->bank()->active()->for($this->team)->create();
    $this->account->currencies()->attach($this->currency, ['balance' => '0']);

    $this->arguments = [
        'team' => $this->team->slug,
        'account' => $this->account->getKey(),
        'amount' => '1500,50',
        'type' => TransactionType::Expense->value,
        'category' => Category::casesFor(TransactionType::Expense)[0]->value,
        'frequency' => Frequency::Monthly->value,
        'interval' => 1,
        'starts_on' => '2026-05-31',
        'time_of_day' => '09:30',
        'timezone' => 'America/Montevideo',
        'description' => 'Rent',
    ];
});

it('creates a rule with the currency inferred and its next due date', function (): void {
    $tag = Tag::factory()->for($this->team)->create(['name' => 'Home']);

    ContamosServer::actingAs($this->user)
        ->tool(CreateRecurringRule::class, [...$this->arguments, 'tags' => ['home']])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('rule.currency', 'UYU')
            ->where('rule.amount', '1500.50')
            ->where('rule.next_due_date', '2026-05-31')
            ->where('rule.status', 'active')
            ->where('rule.tags.0.id', $tag->getKey())
            ->etc());

    $rule = RecurringRule::query()->sole();

    expect($rule->team_id)->toBe($this->team->getKey())
        ->and($rule->currency_id)->toBe($this->currency->getKey())
        ->and($rule->user_id)->toBe($this->user->getKey())
        ->and($rule->time_of_day)->toBe('09:30:00')
        ->and($rule->tags->modelKeys())->toBe([$tag->getKey()])
        ->and($rule->next_due_date?->toDateString())->toBe('2026-05-31');
});

it('takes a numeric amount as its decimal string', function (): void {
    ContamosServer::actingAs($this->user)
        ->tool(CreateRecurringRule::class, [...$this->arguments, 'amount' => 1500])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('rule.amount', '1500.00')
            ->etc());

    expect(RecurringRule::query()->sole()->amount)->toBe('1500.000000000000000000');
});

it('refuses a backdated start with the web rules', function (): void {
    ContamosServer::actingAs($this->user)
        ->tool(CreateRecurringRule::class, [...$this->arguments, 'starts_on' => '2026-05-01'])
        ->assertHasErrors(['start date']);

    expect(RecurringRule::query()->count())->toBe(0);
});

it('refuses a team whose plan lacks recurring transactions', function (): void {
    Pennant::for($this->team)->deactivate(Feature::RecurTransactions);

    ContamosServer::actingAs($this->user)
        ->tool(CreateRecurringRule::class, $this->arguments)
        ->assertHasErrors(["This team's plan does not allow this"]);

    expect(RecurringRule::query()->count())->toBe(0);
});

it('refuses another team\'s account', function (): void {
    $foreign = Account::factory()->bank()->active()->create();

    ContamosServer::actingAs($this->user)
        ->tool(CreateRecurringRule::class, [...$this->arguments, 'account' => $foreign->getKey()])
        ->assertHasErrors(['Account not found.']);
});
