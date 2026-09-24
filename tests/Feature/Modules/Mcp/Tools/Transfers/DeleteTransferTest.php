<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Actions\Team\CreateTransfer as CreateTransferAction;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Finance\Models\Team\Transfer;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\Transfers\DeleteTransfer;
use Illuminate\Testing\Fluent\AssertableJson;

/**
 * Feature tests for \App\Modules\Mcp\Tools\Transfers\DeleteTransfer.
 */
it('deletes the transfer with both legs and its fees, restoring both balances', function (): void {
    [$user] = mcpMember();
    [$team, $origin, $destination, $currency] = transferSides();
    memberOf($team, Role::Owner, $user);
    subscribeToPro($team);
    $transfer = (new CreateTransferAction)->for($team)->by($user)->using(transferAttributes($origin, $destination, $currency, $currency, [
        'destination_fee' => '1.00',
        'origin_fee' => '2.00',
    ]));

    expect(balanceOf($origin, $currency))->toBe(73.0)
        ->and(balanceOf($destination, $currency))->toBe(24.0);

    ContamosServer::actingAs($user)
        ->tool(DeleteTransfer::class, ['team' => $team->slug, 'transfer' => $transfer->getKey()])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('deleted', true)
            ->where('transfer.id', $transfer->getKey())
            ->etc());

    expect(Transfer::query()->count())->toBe(0)
        ->and(Transaction::query()->count())->toBe(0)
        ->and(balanceOf($origin, $currency))->toBe(100.0)
        ->and(balanceOf($destination, $currency))->toBe(0.0);
});

it('refuses a transfer of another team as not found', function (): void {
    [$user, $team] = mcpMember();
    [$other, $origin, $destination, $currency] = transferSides();
    $transfer = (new CreateTransferAction)->for($other)->using(transferAttributes($origin, $destination, $currency, $currency));

    ContamosServer::actingAs($user)
        ->tool(DeleteTransfer::class, ['team' => $team->slug, 'transfer' => $transfer->getKey()])
        ->assertHasErrors(['Transfer not found.']);

    expect(Transfer::query()->whereKey($transfer->getKey())->exists())->toBeTrue()
        ->and(balanceOf($origin, $currency))->toBe(75.0);
});

it('is a destructive tool', function (): void {
    expect(new DeleteTransfer()->annotations())->toMatchArray(['destructiveHint' => true]);
});
