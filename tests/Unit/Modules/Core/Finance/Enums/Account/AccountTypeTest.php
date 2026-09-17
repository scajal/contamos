<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Enums\Account\AccountType;
use Illuminate\Support\Facades\App;

/**
 * Unit tests for \App\Modules\Core\Finance\Enums\Account\AccountType.
 */
it('translates the account type label', function (AccountType $type, string $label): void {
    App::setLocale('en');

    expect($type->getLabel())->toBe($label);
})->with([
    'bank' => [AccountType::Bank, 'Bank'],
    'cash' => [AccountType::Cash, 'Cash'],
    'crypto' => [AccountType::Crypto, 'Crypto'],
]);
