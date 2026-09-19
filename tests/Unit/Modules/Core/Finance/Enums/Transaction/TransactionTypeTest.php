<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use Illuminate\Support\Facades\App;

/**
 * Unit tests for \App\Modules\Core\Finance\Enums\Transaction\TransactionType.
 */
it('translates the transaction type label', function (TransactionType $type, string $label): void {
    App::setLocale('en');

    expect($type->getLabel())->toBe($label);
})->with([
    'expense' => [TransactionType::Expense, 'Expense'],
    'income' => [TransactionType::Income, 'Income'],
    'opening balance' => [TransactionType::OpeningBalance, 'Opening balance'],
    'transfer in' => [TransactionType::TransferIn, 'Transfer in'],
    'transfer out' => [TransactionType::TransferOut, 'Transfer out'],
]);

it('knows which types are a leg of a transfer', function (TransactionType $type, bool $isTransfer): void {
    expect($type->isTransfer())->toBe($isTransfer);
})->with([
    'expense' => [TransactionType::Expense, false],
    'income' => [TransactionType::Income, false],
    'opening balance' => [TransactionType::OpeningBalance, false],
    'transfer in' => [TransactionType::TransferIn, true],
    'transfer out' => [TransactionType::TransferOut, true],
]);
