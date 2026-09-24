<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\Reports\Summarize;
use Carbon\CarbonImmutable;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Pennant\Feature as Pennant;

/**
 * Feature tests for \App\Modules\Mcp\Tools\Reports\Summarize.
 */
beforeEach(function (): void {
    [$this->user, $this->team] = mcpMember();
    $this->peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS', 'decimal_places' => 2]);
    $this->dollar = Currency::factory()->active()->fiat()->create(['code' => 'USD', 'decimal_places' => 2]);
    $this->bank = Account::factory()->active()->for($this->team)->create(['name' => 'Bank']);
    $this->cash = Account::factory()->active()->for($this->team)->create(['name' => 'Cash']);
    $this->food = Tag::factory()->active()->for($this->team)->create(['name' => 'Food']);
    $this->day = CarbonImmutable::parse('2026-08-10 12:00:00');

    $this->groceries = $groceries = Transaction::factory()->expense()->for($this->bank)->for($this->peso)->create(['amount' => '40', 'category' => Category::Food, 'occurred_at' => $this->day]);
    $groceries->attachTags([$this->food->getKey()]);
    Transaction::factory()->expense()->for($this->cash)->for($this->peso)->create(['amount' => '60', 'category' => Category::Food, 'occurred_at' => $this->day]);
    Transaction::factory()->income()->for($this->bank)->for($this->peso)->create(['amount' => '1000', 'category' => Category::Salary, 'occurred_at' => $this->day]);
    Transaction::factory()->expense()->for($this->bank)->for($this->dollar)->create(['amount' => '5', 'category' => Category::Food, 'occurred_at' => $this->day]);

    // Outside the range, on an archived account and a transfer
    // leg: none of them counts.
    Transaction::factory()->expense()->for($this->bank)->for($this->peso)->create(['amount' => '999', 'category' => Category::Food, 'occurred_at' => $this->day->addMonth()]);
    Transaction::factory()->expense()->for(Account::factory()->inactive()->for($this->team)->create())->for($this->peso)->create(['amount' => '999', 'occurred_at' => $this->day]);
    Transaction::factory()->transferOut()->for($this->bank)->for($this->peso)->create(['amount' => '999', 'occurred_at' => $this->day]);
});

/**
 * Summarize the August range of the test team.
 *
 * @param  array<string, mixed>  $arguments
 */
function summarizeAugust(array $arguments): TestResponse
{
    return ContamosServer::actingAs(test()->user)->tool(Summarize::class, [
        'team' => test()->team->slug,
        'from' => '2026-08-01',
        'to' => '2026-08-31',
        ...$arguments,
    ]);
}

it('totals by category, keeping currencies apart', function (): void {
    summarizeAugust(['group_by' => 'category'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('from', '2026-08-01')
            ->where('to', '2026-08-31')
            ->has('currencies', 2)
            ->where('currencies.0.currency', 'ARS')
            ->has('currencies.0.groups', 2)
            ->where('currencies.0.groups.0.category.value', Category::Food->value)
            ->where('currencies.0.groups.0.expenses', '100.00')
            ->where('currencies.0.groups.1.category.value', Category::Salary->value)
            ->where('currencies.0.groups.1.income', '1000.00')
            ->where('currencies.0.total', ['income' => '1000.00', 'expenses' => '100.00', 'net' => '900.00'])
            ->where('currencies.1.currency', 'USD')
            ->where('currencies.1.total.expenses', '5.00')
            ->etc());
});

it('totals by account', function (): void {
    summarizeAugust(['group_by' => 'account', 'currency' => 'ars'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->has('currencies', 1)
            ->has('currencies.0.groups', 2)
            ->where('currencies.0.groups.0.account', ['id' => $this->cash->getKey(), 'name' => 'Cash'])
            ->where('currencies.0.groups.0.expenses', '60.00')
            ->where('currencies.0.groups.1.account', ['id' => $this->bank->getKey(), 'name' => 'Bank'])
            ->where('currencies.0.groups.1.expenses', '40.00')
            ->where('currencies.0.groups.1.income', '1000.00')
            ->etc());
});

it('totals by tag with untagged money last and each movement counted once in the total', function (): void {
    $market = Tag::factory()->active()->for($this->team)->create(['name' => 'Market']);
    $this->groceries->attachTags([$market->getKey()]);

    summarizeAugust(['group_by' => 'tag', 'type' => 'expense', 'currency' => 'ARS'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->has('currencies.0.groups', 3)
            ->where('currencies.0.groups.0.tag', ['id' => $this->food->getKey(), 'name' => 'Food'])
            ->where('currencies.0.groups.0.expenses', '40.00')
            ->where('currencies.0.groups.1.tag', ['id' => $market->getKey(), 'name' => 'Market'])
            ->where('currencies.0.groups.1.expenses', '40.00')
            ->where('currencies.0.groups.2.tag', ['id' => null, 'name' => null])
            ->where('currencies.0.groups.2.expenses', '60.00')
            ->where('currencies.0.groups.2.income', '0.00')
            ->where('currencies.0.total', ['income' => '0.00', 'expenses' => '100.00', 'net' => '-100.00'])
            ->etc());
});

it('narrows to one account', function (): void {
    summarizeAugust(['group_by' => 'category', 'account' => $this->cash->getKey()])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->has('currencies', 1)
            ->where('currencies.0.total', ['income' => '0.00', 'expenses' => '60.00', 'net' => '-60.00'])
            ->etc());
});

it('refuses an account of another team', function (): void {
    [, $other] = mcpMember();

    summarizeAugust(['group_by' => 'category', 'account' => Account::factory()->active()->for($other)->create()->getKey()])
        ->assertHasErrors(['Account not found.']);
});

it('refuses a currency code that names no currency', function (): void {
    summarizeAugust(['group_by' => 'category', 'currency' => 'ZZZ'])
        ->assertHasErrors(['Unknown currency "ZZZ".']);
});

it('refuses an unknown grouping and a backwards range', function (): void {
    summarizeAugust(['group_by' => 'member'])->assertHasErrors(['group by']);
    summarizeAugust(['group_by' => 'category', 'from' => '2026-09-01'])->assertHasErrors(['to']);
});

it('moves a start before the plan\'s history window up to it', function (): void {
    [$user, $team] = memberWithTeam();
    Pennant::for($team)->activate(Feature::UseMcp);
    $windowStart = CarbonImmutable::now()->startOfMonth()->subMonths(Feature::ViewHistory->limitOn($team->plan()) - 1);

    ContamosServer::actingAs($user)
        ->tool(Summarize::class, [
            'team' => $team->slug,
            'from' => $windowStart->subYear()->toDateString(),
            'to' => CarbonImmutable::now()->toDateString(),
            'group_by' => 'category',
        ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('from', $windowStart->toDateString())
            ->etc());

    ContamosServer::actingAs($user)
        ->tool(Summarize::class, [
            'team' => $team->slug,
            'from' => $windowStart->subYear()->toDateString(),
            'to' => $windowStart->subMonth()->toDateString(),
            'group_by' => 'category',
        ])
        ->assertHasErrors(['only shows history from']);
});

it('is a read-only tool', function (): void {
    expect(new Summarize()->annotations())->toMatchArray(['readOnlyHint' => true]);
});
