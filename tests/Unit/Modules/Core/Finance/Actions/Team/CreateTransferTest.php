<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Actions\Team\CreateTransfer;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Illuminate\Support\Facades\App;

/**
 * Unit tests for \App\Modules\Core\Finance\Actions\Team\CreateTransfer.
 */
it('throws when a team was not provided', function (): void {
    [, $origin, $destination, $currency] = transferSides();

    (new CreateTransfer)->using(transferAttributes($origin, $destination, $currency, $currency));
})->throws(\Exception::class, 'A transfer cannot be created without a team.');

it('creates both legs with their type, account, currency, amount, decimal places, and quote', function (): void {
    [$team, $origin, $destination, $currency] = transferSides();

    $transfer = (new CreateTransfer)
        ->for($team)
        ->using(transferAttributes($origin, $destination, $currency, $currency, [
            'amount_received' => '24.00',
            'origin_quote' => '2',
        ]));

    $transfer->load('transactions');

    expect($transfer->transactions)->toHaveCount(2)
        ->and($transfer->originLeg()?->type)->toBe(TransactionType::TransferOut)
        ->and($transfer->originLeg()?->account_id)->toBe($origin->getKey())
        ->and($transfer->originLeg()?->currency_id)->toBe($currency->getKey())
        ->and((float) $transfer->originLeg()?->amount)->toBe(25.0)
        ->and($transfer->originLeg()?->decimal_places)->toBe(2)
        ->and((float) $transfer->originLeg()?->quote)->toBe(2.0)
        ->and($transfer->originLeg()?->description)->toBe('Rent savings')
        ->and($transfer->destinationLeg()?->type)->toBe(TransactionType::TransferIn)
        ->and($transfer->destinationLeg()?->account_id)->toBe($destination->getKey())
        ->and((float) $transfer->destinationLeg()?->amount)->toBe(24.0)
        ->and((float) $transfer->destinationLeg()?->quote)->toBe(1.0);
});

it('applies the balances of a transfer without fees', function (): void {
    [$team, $origin, $destination, $currency] = transferSides();

    (new CreateTransfer)
        ->for($team)
        ->using(transferAttributes($origin, $destination, $currency, $currency));

    expect(balanceOf($origin, $currency))->toBe(75.0)
        ->and(balanceOf($destination, $currency))->toBe(25.0);
});

it('applies the balances of a transfer with a fee on the origin', function (): void {
    [$team, $origin, $destination, $currency] = transferSides();

    (new CreateTransfer)
        ->for($team)
        ->using(transferAttributes($origin, $destination, $currency, $currency, ['origin_fee' => '1.50']));

    expect(balanceOf($origin, $currency))->toBe(73.5)
        ->and(balanceOf($destination, $currency))->toBe(25.0);
});

it('applies the balances of a transfer with a fee on the destination', function (): void {
    [$team, $origin, $destination, $currency] = transferSides();

    (new CreateTransfer)
        ->for($team)
        ->using(transferAttributes($origin, $destination, $currency, $currency, ['destination_fee' => '2.00']));

    expect(balanceOf($origin, $currency))->toBe(75.0)
        ->and(balanceOf($destination, $currency))->toBe(23.0);
});

it('applies the balances of a transfer with a fee on both sides', function (): void {
    App::setLocale('en');

    [$team, $origin, $destination, $currency] = transferSides();

    $transfer = (new CreateTransfer)
        ->for($team)
        ->using(transferAttributes($origin, $destination, $currency, $currency, [
            'destination_fee' => '2.00',
            'origin_fee' => '1.50',
        ]));

    $transfer->load('transactions');

    expect(balanceOf($origin, $currency))->toBe(73.5)
        ->and(balanceOf($destination, $currency))->toBe(23.0)
        ->and($transfer->transactions)->toHaveCount(4)
        ->and($transfer->originFee()?->type)->toBe(TransactionType::Expense)
        ->and($transfer->originFee()?->description)->toBe('Transfer fee')
        ->and((float) $transfer->originFee()?->amount)->toBe(1.5)
        ->and($transfer->destinationFee()?->account_id)->toBe($destination->getKey())
        ->and((float) $transfer->destinationFee()?->amount)->toBe(2.0);
});

it('applies the balances of a cross currency transfer', function (): void {
    [$team, $origin, $destination] = transferSides();
    $originCurrency = Currency::factory()->fiat()->create(['decimal_places' => 2]);
    $destinationCurrency = Currency::factory()->fiat()->create(['decimal_places' => 0]);
    $origin->currencies()->attach($originCurrency, ['balance' => '500.00']);
    $destination->currencies()->attach($destinationCurrency, ['balance' => '0']);

    $transfer = (new CreateTransfer)
        ->for($team)
        ->using(transferAttributes($origin, $destination, $originCurrency, $destinationCurrency, [
            'amount_received' => '4000',
            'amount_sent' => '100.00',
            'destination_quote' => '40',
        ]));

    $transfer->load('transactions');

    expect(balanceOf($origin, $originCurrency))->toBe(400.0)
        ->and(balanceOf($destination, $destinationCurrency))->toBe(4000.0)
        ->and($transfer->destinationLeg()?->decimal_places)->toBe(0);
});

it('associates the user that made the transfer with the transfer and its transactions', function (): void {
    [$team, $origin, $destination, $currency] = transferSides();
    $user = User::factory()->create();

    $transfer = (new CreateTransfer)
        ->for($team)
        ->by($user)
        ->using(transferAttributes($origin, $destination, $currency, $currency, ['origin_fee' => '1.00']));

    $transfer->load('transactions');

    expect($transfer->user?->is($user))->toBeTrue()
        ->and($transfer->transactions->pluck('user_id')->unique()->all())->toBe([$user->getKey()]);
});

it('attaches the tags to the transfer and to every owned transaction', function (): void {
    [$team, $origin, $destination, $currency] = transferSides();
    $tag = Tag::factory()->active()->for($team)->create();

    $transfer = (new CreateTransfer)
        ->for($team)
        ->using(transferAttributes($origin, $destination, $currency, $currency, ['origin_fee' => '1.00']), [$tag->getKey()]);

    $transfer->load('transactions');

    expect($transfer->tags()->pluck($tag->qualifyColumn($tag->getKeyName()))->all())->toBe([$tag->getKey()]);

    foreach ($transfer->transactions as $transaction) {
        expect($transaction->tags()->pluck($tag->qualifyColumn($tag->getKeyName()))->all())->toBe([$tag->getKey()]);
    }
});

it('throws when a tag belongs to another team', function (): void {
    [$team, $origin, $destination, $currency] = transferSides();
    $tag = Tag::factory()->active()->for(Team::factory())->create();

    (new CreateTransfer)
        ->for($team)
        ->using(transferAttributes($origin, $destination, $currency, $currency), [$tag->getKey()]);
})->throws(\InvalidArgumentException::class);

it('refuses an account belonging to another team', function (): void {
    [$team, $origin, , $currency] = transferSides();
    $otherAccount = Account::factory()->bank()->for(Team::factory())->create();

    (new CreateTransfer)
        ->for($team)
        ->using(transferAttributes($origin, $otherAccount, $currency, $currency));
})->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
