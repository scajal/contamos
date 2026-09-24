<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Catalog\Models\Currency\Quote;
use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetAlertKind;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transfer;
use App\Modules\Core\Finance\Notifications\Team\BudgetAlertNotification;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\Transfers\CreateTransfer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\Fluent\AssertableJson;

/**
 * Feature tests for \App\Modules\Mcp\Tools\Transfers\CreateTransfer.
 */

/**
 * Create a Pro member with an active account holding the given
 * active currencies, each with a rate recorded before the dates
 * the tests use.
 *
 * @param  array<string, string>  $rates
 * @return array{0: \App\Modules\Core\Identity\Models\User, 1: \App\Modules\Core\Identity\Models\Team, 2: array<int, \App\Modules\Core\Catalog\Models\Currency>}
 */
function createTransferToolMember(array $rates = ['1']): array
{
    [$user, $team] = mcpMember();

    $currencies = collect($rates)->map(function (string $rate): Currency {
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        Quote::factory()->for($currency)->create(['observed_at' => '2026-08-01 00:00:00', 'rate' => $rate]);

        return $currency;
    })->values()->all();

    return [$user, $team, $currencies];
}

/**
 * Create an active account of the team holding the currency
 * with the given balance.
 */
function createTransferToolAccount(Team $team, Currency $currency, string $balance): Account
{
    $account = Account::factory()->bank()->active()->for($team)->create();
    $account->currencies()->attach($currency, ['balance' => $balance]);

    return $account;
}

it('records a same-currency transfer with a fee, inferring the currencies and the amount received', function (): void {
    [$user, $team, [$currency]] = createTransferToolMember();
    $origin = createTransferToolAccount($team, $currency, '100.00');
    $destination = createTransferToolAccount($team, $currency, '0.00');
    $tag = Tag::factory()->active()->for($team)->create(['name' => 'Savings']);

    ContamosServer::actingAs($user)
        ->tool(CreateTransfer::class, [
            'team' => $team->slug,
            'origin_account' => $origin->getKey(),
            'destination_account' => $destination->getKey(),
            'amount_sent' => '25.00',
            'origin_fee' => '1.50',
            'occurred_at' => '2026-08-14',
            'description' => 'Rent savings',
            'tags' => ['savings'],
        ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('transfer.description', 'Rent savings')
            ->where('transfer.occurred_at', '2026-08-14T12:00:00+00:00')
            ->where('transfer.origin.account.id', $origin->getKey())
            ->where('transfer.origin.currency', $currency->code)
            ->where('transfer.origin.amount', '25.00')
            ->where('transfer.origin.quote', '1')
            ->where('transfer.origin.fee', '1.50')
            ->where('transfer.destination.account.id', $destination->getKey())
            ->where('transfer.destination.amount', '25.00')
            ->where('transfer.destination.fee', null)
            ->where('transfer.tags.0.name', 'Savings')
            ->etc());

    $transfer = Transfer::query()->sole();

    expect(balanceOf($origin, $currency))->toBe(73.5)
        ->and(balanceOf($destination, $currency))->toBe(25.0)
        ->and($transfer->user_id)->toBe($user->getKey())
        ->and($transfer->transactions()->where('type', TransactionType::Expense)->sole()->amount)->toBe('1.500000000000000000')
        ->and($transfer->tags()->pluck($tag->qualifyColumn($tag->getKeyName()))->all())->toBe([$tag->getKey()]);
});

it('records a cross-currency transfer with the rates recorded for the date', function (): void {
    [$user, $team, [$dollar, $euro]] = createTransferToolMember(['1', '1.1']);
    $origin = createTransferToolAccount($team, $dollar, '100.00');
    $destination = createTransferToolAccount($team, $euro, '10.00');

    ContamosServer::actingAs($user)
        ->tool(CreateTransfer::class, [
            'team' => $team->slug,
            'origin_account' => $origin->getKey(),
            'destination_account' => $destination->getKey(),
            'amount_sent' => '50.00',
            'amount_received' => '45.00',
            'destination_currency' => $euro->code,
            'destination_fee' => '1.00',
            'occurred_at' => '2026-08-14 18:30',
            'timezone' => 'America/Montevideo',
        ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('transfer.occurred_at', '2026-08-14T21:30:00+00:00')
            ->where('transfer.occurred_at_local', '2026-08-14T18:30:00-03:00')
            ->where('transfer.origin.currency', $dollar->code)
            ->where('transfer.origin.amount', '50.00')
            ->where('transfer.destination.currency', $euro->code)
            ->where('transfer.destination.amount', '45.00')
            ->where('transfer.destination.quote', '1.1')
            ->where('transfer.destination.fee', '1.00')
            ->etc());

    expect(balanceOf($origin, $dollar))->toBe(50.0)
        ->and(balanceOf($destination, $euro))->toBe(54.0);
});

it('requires the amount received across currencies', function (): void {
    [$user, $team, [$dollar, $euro]] = createTransferToolMember(['1', '1.1']);
    $origin = createTransferToolAccount($team, $dollar, '100.00');
    $destination = createTransferToolAccount($team, $euro, '0.00');

    ContamosServer::actingAs($user)
        ->tool(CreateTransfer::class, [
            'team' => $team->slug,
            'origin_account' => $origin->getKey(),
            'destination_account' => $destination->getKey(),
            'amount_sent' => '50.00',
            'occurred_at' => '2026-08-14',
        ])
        ->assertHasErrors(['amount received']);

    expect(Transfer::query()->count())->toBe(0);
});

it('rejects a transfer to the same account, like the web', function (): void {
    [$user, $team, [$currency]] = createTransferToolMember();
    $account = createTransferToolAccount($team, $currency, '100.00');

    ContamosServer::actingAs($user)
        ->tool(CreateTransfer::class, [
            'team' => $team->slug,
            'origin_account' => $account->getKey(),
            'destination_account' => $account->getKey(),
            'amount_sent' => '25.00',
            'occurred_at' => '2026-08-14',
        ])
        ->assertHasErrors(['must be different']);

    expect(Transfer::query()->count())->toBe(0)
        ->and(balanceOf($account, $currency))->toBe(100.0);
});

it('rejects a currency the account does not hold', function (): void {
    [$user, $team, [$dollar, $euro]] = createTransferToolMember(['1', '1.1']);
    $origin = createTransferToolAccount($team, $dollar, '100.00');
    $destination = createTransferToolAccount($team, $dollar, '0.00');

    ContamosServer::actingAs($user)
        ->tool(CreateTransfer::class, [
            'team' => $team->slug,
            'origin_account' => $origin->getKey(),
            'destination_account' => $destination->getKey(),
            'amount_sent' => '25.00',
            'destination_currency' => $euro->code,
            'occurred_at' => '2026-08-14',
        ])
        ->assertHasErrors(["does not hold {$euro->code}"]);

    expect(Transfer::query()->count())->toBe(0);
});

it('rejects a missing exchange rate by naming the argument to pass', function (): void {
    [$user, $team] = mcpMember();
    $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
    $origin = createTransferToolAccount($team, $currency, '100.00');
    $destination = createTransferToolAccount($team, $currency, '0.00');

    ContamosServer::actingAs($user)
        ->tool(CreateTransfer::class, [
            'team' => $team->slug,
            'origin_account' => $origin->getKey(),
            'destination_account' => $destination->getKey(),
            'amount_sent' => '25.00',
            'occurred_at' => '2026-08-14',
        ])
        ->assertHasErrors(['`origin_quote`']);
});

it('refuses an account of another team as not found', function (): void {
    [$user, $team, [$currency]] = createTransferToolMember();
    $origin = createTransferToolAccount($team, $currency, '100.00');
    [, $foreign] = transferSides();

    ContamosServer::actingAs($user)
        ->tool(CreateTransfer::class, [
            'team' => $team->slug,
            'origin_account' => $origin->getKey(),
            'destination_account' => $foreign->getKey(),
            'amount_sent' => '25.00',
            'occurred_at' => '2026-08-14',
        ])
        ->assertHasErrors(['Destination account not found.']);

    expect(Transfer::query()->count())->toBe(0);
});

it('alerts when its fee pushes a budget over', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-15 12:00:00'));
    [$user, $team, [$currency]] = createTransferToolMember();
    $origin = createTransferToolAccount($team, $currency, '100.00');
    $destination = createTransferToolAccount($team, $currency, '0.00');
    BudgetPeriod::factory()->forCategory(Category::Other)->running()->for($team)->create([
        'amount' => '1',
        $currency->getForeignKey() => $currency->getKey(),
    ]);
    Notification::fake();

    ContamosServer::actingAs($user)
        ->tool(CreateTransfer::class, [
            'team' => $team->slug,
            'origin_account' => $origin->getKey(),
            'destination_account' => $destination->getKey(),
            'amount_sent' => '25.00',
            'origin_fee' => '1.50',
            'occurred_at' => '2026-09-15',
        ])
        ->assertOk();

    Notification::assertSentTo($user, BudgetAlertNotification::class, fn (BudgetAlertNotification $notification): bool => $notification->kind === BudgetAlertKind::Over);
});
