<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Http\Controllers\Team\TransactionController;
use App\Modules\Core\Finance\Models\Team\Transaction;

/**
 * Unit tests for \App\Modules\Core\Finance\Http\Controllers\Team\TransactionController.
 */
it('writes an FX rate that has no decimal point as-is', function (): void {
    $transaction = new Transaction;
    $transaction->mergeCasts(['quote' => 'string']);
    $transaction->forceFill(['quote' => '2']);

    $exportQuote = new ReflectionMethod(TransactionController::class, 'exportQuote');

    expect($exportQuote->invoke(new TransactionController, $transaction))->toBe('2');
});
