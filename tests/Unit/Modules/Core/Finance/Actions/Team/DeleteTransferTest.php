<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Actions\Team\CreateTransfer;
use App\Modules\Core\Finance\Actions\Team\DeleteTransfer;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Finance\Models\Team\Transfer;

/**
 * Unit tests for \App\Modules\Core\Finance\Actions\Team\DeleteTransfer.
 */
it('throws when a team was not provided', function (): void {
    (new DeleteTransfer)->using(Transfer::factory()->create());
})->throws(\Exception::class, 'A transfer cannot be deleted without a team.');

it('deletes the transfer with every owned transaction and restores the balances', function (): void {
    [$team, $origin, $destination, $currency] = transferSides();
    $transfer = (new CreateTransfer)
        ->for($team)
        ->using(transferAttributes($origin, $destination, $currency, $currency, [
            'destination_fee' => '2.00',
            'origin_fee' => '1.50',
        ]));

    (new DeleteTransfer)
        ->for($team)
        ->using($transfer);

    expect(Transfer::query()->whereKey($transfer->getKey())->exists())->toBeFalse()
        ->and(Transaction::query()->where('transfer_id', $transfer->getKey())->count())->toBe(0)
        ->and(balanceOf($origin, $currency))->toBe(100.0)
        ->and(balanceOf($destination, $currency))->toBe(0.0);
});
