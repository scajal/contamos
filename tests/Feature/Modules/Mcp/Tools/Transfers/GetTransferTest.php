<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Actions\Team\CreateTransfer as CreateTransferAction;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\Transfers\GetTransfer;
use Illuminate\Testing\Fluent\AssertableJson;

/**
 * Feature tests for \App\Modules\Mcp\Tools\Transfers\GetTransfer.
 */
it('shows the transfer with both legs and its fee', function (): void {
    [$user, $team] = mcpMember();
    $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
    $origin = Account::factory()->bank()->active()->for($team)->create(['name' => 'Checking']);
    $destination = Account::factory()->cash()->active()->for($team)->create(['name' => 'Wallet']);
    $origin->currencies()->attach($currency, ['balance' => '100.00']);
    $destination->currencies()->attach($currency, ['balance' => '0.00']);
    $transfer = (new CreateTransferAction)->for($team)->by($user)->using(transferAttributes($origin, $destination, $currency, $currency, [
        'origin_fee' => '2.00',
    ]));
    $transfer->load('transactions');

    ContamosServer::actingAs($user)
        ->tool(GetTransfer::class, ['team' => $team->slug, 'transfer' => $transfer->getKey()])
        ->assertOk()
        ->assertStructuredContent([
            'transfer' => [
                'id' => $transfer->getKey(),
                'description' => 'Rent savings',
                'occurred_at' => '2024-01-01T00:00:00+00:00',
                'origin' => [
                    'transaction_id' => $transfer->originLeg()?->getKey(),
                    'account' => ['id' => $origin->getKey(), 'name' => 'Checking'],
                    'currency' => $currency->code,
                    'amount' => '25.00',
                    'quote' => '1',
                    'fee' => '2.00',
                    'fee_transaction_id' => $transfer->originFee()?->getKey(),
                ],
                'destination' => [
                    'transaction_id' => $transfer->destinationLeg()?->getKey(),
                    'account' => ['id' => $destination->getKey(), 'name' => 'Wallet'],
                    'currency' => $currency->code,
                    'amount' => '25.00',
                    'quote' => '1',
                    'fee' => null,
                    'fee_transaction_id' => null,
                ],
                'tags' => [],
                'recorded_by' => $user->name,
            ],
        ]);
});

it('shows a missing leg as empty instead of failing', function (): void {
    [$user, $team] = mcpMember();
    $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
    $origin = Account::factory()->bank()->active()->for($team)->create(['name' => 'Checking']);
    $destination = Account::factory()->cash()->active()->for($team)->create(['name' => 'Wallet']);
    $origin->currencies()->attach($currency, ['balance' => '100.00']);
    $destination->currencies()->attach($currency, ['balance' => '0.00']);
    $transfer = (new CreateTransferAction)->for($team)->by($user)->using(transferAttributes($origin, $destination, $currency, $currency));
    $transfer->load('transactions');
    $transfer->destinationLeg()?->delete();

    ContamosServer::actingAs($user)
        ->tool(GetTransfer::class, ['team' => $team->slug, 'transfer' => $transfer->getKey()])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('transfer.origin.transaction_id', $transfer->originLeg()?->getKey())
            ->where('transfer.destination', null)
            ->etc());
});

it('refuses a transfer of another team as not found', function (): void {
    [$user, $team] = mcpMember();
    [$other, $origin, $destination, $currency] = transferSides();
    $transfer = (new CreateTransferAction)->for($other)->using(transferAttributes($origin, $destination, $currency, $currency));

    ContamosServer::actingAs($user)
        ->tool(GetTransfer::class, ['team' => $team->slug, 'transfer' => $transfer->getKey()])
        ->assertHasErrors(['Transfer not found.']);
});

it('is a read-only tool', function (): void {
    expect(new GetTransfer()->annotations())->toMatchArray(['readOnlyHint' => true]);
});
