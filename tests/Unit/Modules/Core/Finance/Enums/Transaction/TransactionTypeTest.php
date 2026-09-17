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
]);
