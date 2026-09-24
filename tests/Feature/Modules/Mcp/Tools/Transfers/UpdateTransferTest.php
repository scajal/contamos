<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Catalog\Models\Currency\Quote;
use App\Modules\Core\Finance\Actions\Team\CreateTransfer as CreateTransferAction;
use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetAlertKind;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Transfer;
use App\Modules\Core\Finance\Notifications\Team\BudgetAlertNotification;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\Transfers\UpdateTransfer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\Fluent\AssertableJson;

/**
 * Feature tests for \App\Modules\Mcp\Tools\Transfers\UpdateTransfer.
 */

/**
 * Create a Pro member with two active accounts sharing an active
 * currency and a transfer of 25.00 between them, recorded
 * through the domain action.
 *
 * @param  array<string, mixed>  $overrides
 * @return array{0: \App\Modules\Core\Identity\Models\User, 1: \App\Modules\Core\Identity\Models\Team, 2: \App\Modules\Core\Finance\Models\Team\Account, 3: \App\Modules\Core\Finance\Models\Team\Account, 4: \App\Modules\Core\Catalog\Models\Currency, 5: \App\Modules\Core\Finance\Models\Team\Transfer}
 */
function updateTransferToolSides(array $overrides = []): array
{
    [$user, $team] = mcpMember();
    $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
    $origin = updateTransferToolAccount($team, $currency, '100.00');
    $destination = updateTransferToolAccount($team, $currency, '0.00');

    $transfer = (new CreateTransferAction)->for($team)->by($user)->using(transferAttributes($origin, $destination, $currency, $currency, $overrides));

    return [$user, $team, $origin, $destination, $currency, $transfer->load('transactions')];
}

/**
 * Create an active account of the team holding the currency
 * with the given balance.
 */
function updateTransferToolAccount(Team $team, Currency $currency, string $balance): Account
{
    $account = Account::factory()->bank()->active()->for($team)->create();
    $account->currencies()->attach($currency, ['balance' => $balance]);

    return $account;
}

it('adjusts both legs, keeping their ids, the stored rates and the description', function (): void {
    [$user, $team, $origin, $destination, $currency, $transfer] = updateTransferToolSides();
    $originLegId = $transfer->originLeg()?->getKey();
    $destinationLegId = $transfer->destinationLeg()?->getKey();

    ContamosServer::actingAs($user)
        ->tool(UpdateTransfer::class, [
            'team' => $team->slug,
            'transfer' => $transfer->getKey(),
            'amount_sent' => '40.00',
            'origin_fee' => '2.00',
        ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('transfer.description', 'Rent savings')
            ->where('transfer.origin.transaction_id', $originLegId)
            ->where('transfer.origin.amount', '40.00')
            ->where('transfer.origin.fee', '2.00')
            ->where('transfer.destination.transaction_id', $destinationLegId)
            ->where('transfer.destination.amount', '40.00')
            ->where('transfer.destination.quote', '1')
            ->etc());

    expect(balanceOf($origin, $currency))->toBe(58.0)
        ->and(balanceOf($destination, $currency))->toBe(40.0)
        ->and($transfer->fresh()?->description)->toBe('Rent savings');
});

it('moves the balances when the destination changes account', function (): void {
    [$user, $team, $origin, $destination, $currency, $transfer] = updateTransferToolSides();
    $other = updateTransferToolAccount($team, $currency, '5.00');

    ContamosServer::actingAs($user)
        ->tool(UpdateTransfer::class, [
            'team' => $team->slug,
            'transfer' => $transfer->getKey(),
            'destination_account' => $other->getKey(),
        ])
        ->assertOk();

    expect(balanceOf($origin, $currency))->toBe(75.0)
        ->and(balanceOf($destination, $currency))->toBe(0.0)
        ->and(balanceOf($other, $currency))->toBe(30.0);
});

it('stops charging a fee passed as null', function (): void {
    [$user, $team, $origin, $destination, $currency, $transfer] = updateTransferToolSides(['origin_fee' => '1.00']);

    expect(balanceOf($origin, $currency))->toBe(74.0);

    ContamosServer::actingAs($user)
        ->tool(UpdateTransfer::class, [
            'team' => $team->slug,
            'transfer' => $transfer->getKey(),
            'origin_fee' => null,
        ])
        ->assertOk();

    expect(balanceOf($origin, $currency))->toBe(75.0)
        ->and(balanceOf($destination, $currency))->toBe(25.0)
        ->and($transfer->transactions()->count())->toBe(2);
});

it('resolves the rate recorded for a new date', function (): void {
    [$user, $team, , , $currency, $transfer] = updateTransferToolSides();
    Quote::factory()->for($currency)->create(['observed_at' => '2026-08-01 00:00:00', 'rate' => '1.25']);

    ContamosServer::actingAs($user)
        ->tool(UpdateTransfer::class, [
            'team' => $team->slug,
            'transfer' => $transfer->getKey(),
            'occurred_at' => '2026-08-14',
        ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('transfer.occurred_at', '2026-08-14T12:00:00+00:00')
            ->where('transfer.origin.quote', '1.25')
            ->where('transfer.destination.quote', '1.25')
            ->etc());
});

it('rejects moving both sides onto the same account, like the web', function (): void {
    [$user, $team, $origin, $destination, $currency, $transfer] = updateTransferToolSides();

    ContamosServer::actingAs($user)
        ->tool(UpdateTransfer::class, [
            'team' => $team->slug,
            'transfer' => $transfer->getKey(),
            'destination_account' => $origin->getKey(),
        ])
        ->assertHasErrors(['must be different']);

    expect(balanceOf($origin, $currency))->toBe(75.0)
        ->and(balanceOf($destination, $currency))->toBe(25.0);
});

it('rejects a currency the account does not hold', function (): void {
    [$user, $team, $origin, $destination, $currency, $transfer] = updateTransferToolSides();
    $euro = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);

    ContamosServer::actingAs($user)
        ->tool(UpdateTransfer::class, [
            'team' => $team->slug,
            'transfer' => $transfer->getKey(),
            'destination_currency' => $euro->code,
        ])
        ->assertHasErrors(["does not hold {$euro->code}"]);

    expect(balanceOf($destination, $currency))->toBe(25.0);
});

it('refuses a transfer of another team as not found', function (): void {
    [$user, $team] = mcpMember();
    [$other, $origin, $destination, $currency] = transferSides();
    $transfer = (new CreateTransferAction)->for($other)->using(transferAttributes($origin, $destination, $currency, $currency));

    ContamosServer::actingAs($user)
        ->tool(UpdateTransfer::class, [
            'team' => $team->slug,
            'transfer' => $transfer->getKey(),
            'amount_sent' => '10.00',
        ])
        ->assertHasErrors(['Transfer not found.']);

    expect(Transfer::query()->sole()->originLeg()?->amount)->toBe('25.000000000000000000');
});

it('is a destructive tool', function (): void {
    expect(new UpdateTransfer()->annotations())->toMatchArray(['destructiveHint' => true]);
});

it('alerts when the edit adds a fee that pushes a budget over', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-15 12:00:00'));
    [$user, $team, $origin, $destination, $currency, $transfer] = updateTransferToolSides(['occurred_at' => '2026-09-15 10:00:00']);
    BudgetPeriod::factory()->forCategory(Category::Other)->running()->for($team)->create([
        'amount' => '1',
        $currency->getForeignKey() => $currency->getKey(),
    ]);
    Notification::fake();

    ContamosServer::actingAs($user)
        ->tool(UpdateTransfer::class, [
            'team' => $team->slug,
            'transfer' => $transfer->getKey(),
            'origin_fee' => '2.00',
        ])
        ->assertOk();

    Notification::assertSentTo($user, BudgetAlertNotification::class, fn (BudgetAlertNotification $notification): bool => $notification->kind === BudgetAlertKind::Over);
});
