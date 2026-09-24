<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Finance\Models\Team\Transfer;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\Transactions\ListTransactions;
use Carbon\CarbonImmutable;
use Illuminate\Testing\Fluent\AssertableJson;

/**
 * Feature tests for \App\Modules\Mcp\Tools\Transactions\ListTransactions.
 */

/**
 * Create an account of the team holding a currency with the
 * given code.
 *
 * @return array{0: \App\Modules\Core\Finance\Models\Team\Account, 1: \App\Modules\Core\Catalog\Models\Currency}
 */
function listTransactionsToolAccount(Team $team, string $code = 'ZZA'): array
{
    $currency = Currency::factory()->active()->fiat()->create(['code' => $code, 'decimal_places' => 2]);
    $account = Account::factory()->bank()->active()->for($team)->create();
    $account->currencies()->attach($currency, ['balance' => '0']);

    return [$account, $currency];
}

/**
 * Call the tool and get its structured content.
 *
 * @param  array<string, mixed>  $arguments
 * @return array<string, mixed>
 */
function listTransactionsToolContent(User $user, array $arguments): array
{
    $content = [];

    ContamosServer::actingAs($user)
        ->tool(ListTransactions::class, $arguments)
        ->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) use (&$content): AssertableJson {
            $content = $json->toArray();

            return $json->etc();
        });

    return $content;
}

it('lists the team\'s transactions newest first with signed amounts', function (): void {
    [$user, $team] = mcpMember();
    [$account, $currency] = listTransactionsToolAccount($team);
    $older = Transaction::factory()->income()->for($account)->create(['amount' => '10', 'currency_id' => $currency->getKey(), 'decimal_places' => 2, 'occurred_at' => '2026-08-01 12:00:00']);
    $newer = Transaction::factory()->expense()->for($account)->create(['amount' => '3.5', 'currency_id' => $currency->getKey(), 'decimal_places' => 2, 'occurred_at' => '2026-08-02 12:00:00']);
    [$foreignAccount, $foreignCurrency] = listTransactionsToolAccount(Team::factory()->create(), 'ZZB');
    Transaction::factory()->for($foreignAccount)->create(['currency_id' => $foreignCurrency->getKey()]);

    ContamosServer::actingAs($user)
        ->tool(ListTransactions::class, ['team' => $team->slug])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->has('items', 2)
            ->where('items.0.id', $newer->getKey())
            ->where('items.0.amount', '-3.50')
            ->where('items.0.currency', 'ZZA')
            ->where('items.0.account.id', $account->getKey())
            ->where('items.1.id', $older->getKey())
            ->where('items.1.amount', '10.00')
            ->where('next_cursor', null));
});

it('filters like the web listing', function (): void {
    [$user, $team] = mcpMember();
    [$account, $currency] = listTransactionsToolAccount($team);
    [$otherAccount, $otherCurrency] = listTransactionsToolAccount($team, 'ZZB');
    $tag = Tag::factory()->for($team)->create(['name' => 'Trip', 'is_active' => false]);

    $food = Transaction::factory()->expense()->for($account)->create(['category' => Category::Food, 'currency_id' => $currency->getKey(), 'description' => 'Pizza night', 'occurred_at' => '2026-08-10 12:00:00']);
    $food->attachTags([$tag->getKey()]);
    $salary = Transaction::factory()->income()->for($otherAccount)->create(['category' => Category::Salary, 'currency_id' => $otherCurrency->getKey(), 'occurred_at' => '2026-07-10 12:00:00']);
    $leg = Transaction::factory()->transferOut()->for($account)->create(['currency_id' => $currency->getKey(), 'transfer_id' => Transfer::factory()->for($team)->create()->getKey(), 'occurred_at' => '2026-08-11 12:00:00']);

    $ids = fn (array $arguments): array => collect(listTransactionsToolContent($user, ['team' => $team->slug, ...$arguments])['items'] ?? [])
        ->pluck('id')
        ->all();

    expect($ids(['type' => 'transfer']))->toBe([$leg->getKey()])
        ->and($ids(['type' => 'income']))->toBe([$salary->getKey()])
        ->and($ids(['account' => $otherAccount->getKey()]))->toBe([$salary->getKey()])
        ->and($ids(['category' => 'food']))->toBe([$food->getKey()])
        ->and($ids(['currency' => 'zzb']))->toBe([$salary->getKey()])
        ->and($ids(['tags' => ['trip']]))->toBe([$food->getKey()])
        ->and($ids(['from' => '2026-08-01', 'to' => '2026-08-10']))->toBe([$food->getKey()])
        ->and($ids(['search' => 'pizza']))->toBe([$food->getKey()]);
});

it('includes the transfer and recurring rule a row belongs to', function (): void {
    [$user, $team] = mcpMember();
    [$account, $currency] = listTransactionsToolAccount($team);
    $transfer = Transfer::factory()->for($team)->create();
    Transaction::factory()->transferIn()->for($account)->create(['currency_id' => $currency->getKey(), 'transfer_id' => $transfer->getKey()]);

    ContamosServer::actingAs($user)
        ->tool(ListTransactions::class, ['team' => $team->slug])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('items.0.transfer_id', $transfer->getKey())
            ->where('items.0.recurring_rule_id', null)
            ->etc());
});

it('pages through the list with a cursor', function (): void {
    [$user, $team] = mcpMember();
    [$account, $currency] = listTransactionsToolAccount($team);
    $start = CarbonImmutable::parse('2026-01-01 12:00:00');

    foreach (range(0, 51) as $day) {
        Transaction::factory()->expense()->for($account)->create(['currency_id' => $currency->getKey(), 'occurred_at' => $start->addDays($day)]);
    }

    $first = listTransactionsToolContent($user, ['team' => $team->slug]);

    expect($first['items'] ?? [])->toHaveCount(50)
        ->and($first['next_cursor'] ?? null)->toBeString();

    $second = listTransactionsToolContent($user, ['team' => $team->slug, 'cursor' => $first['next_cursor'] ?? null]);

    expect($second['items'] ?? [])->toHaveCount(2)
        ->and($second)->toHaveKey('next_cursor', null)
        ->and($second['items'][1]['occurred_at'] ?? null)->toBe('2026-01-01T12:00:00+00:00');
});

it('rejects an invalid filter value', function (): void {
    [$user, $team] = mcpMember();

    ContamosServer::actingAs($user)
        ->tool(ListTransactions::class, ['team' => $team->slug, 'from' => 'yesterday'])
        ->assertHasErrors(['from']);
});

it('is a read-only tool', function (): void {
    expect(new ListTransactions()->annotations())->toMatchArray(['readOnlyHint' => true]);
});
