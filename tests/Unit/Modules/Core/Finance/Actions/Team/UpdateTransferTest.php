<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Actions\Team\CreateTransfer;
use App\Modules\Core\Finance\Actions\Team\UpdateTransfer;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transfer;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;

/**
 * Unit tests for \App\Modules\Core\Finance\Actions\Team\UpdateTransfer.
 */
it('throws when a team was not provided', function (): void {
    [, $origin, $destination, $currency] = transferSides();

    (new UpdateTransfer)->using(Transfer::factory()->create(), transferAttributes($origin, $destination, $currency, $currency));
})->throws(\Exception::class, 'A transfer cannot be updated without a team.');

it('moves the balances when the amounts change and keeps the leg ids', function (): void {
    [$team, $origin, $destination, $currency] = transferSides();
    $transfer = (new CreateTransfer)
        ->for($team)
        ->using(transferAttributes($origin, $destination, $currency, $currency));
    $transfer->load('transactions');
    $originLegId = $transfer->originLeg()?->getKey();
    $destinationLegId = $transfer->destinationLeg()?->getKey();

    $updated = (new UpdateTransfer)
        ->for($team)
        ->using($transfer, transferAttributes($origin, $destination, $currency, $currency, [
            'amount_received' => '40.00',
            'amount_sent' => '40.00',
        ]));

    $updated->load('transactions');

    expect(balanceOf($origin, $currency))->toBe(60.0)
        ->and(balanceOf($destination, $currency))->toBe(40.0)
        ->and($updated->originLeg()?->getKey())->toBe($originLegId)
        ->and($updated->destinationLeg()?->getKey())->toBe($destinationLegId);
});

it('moves the balances across accounts when a side changes account', function (): void {
    [$team, $origin, $destination, $currency] = transferSides();
    $other = Account::factory()->bank()->for($team)->create();
    $other->currencies()->attach($currency, ['balance' => '10.00']);
    $transfer = (new CreateTransfer)
        ->for($team)
        ->using(transferAttributes($origin, $destination, $currency, $currency));

    (new UpdateTransfer)
        ->for($team)
        ->using($transfer, transferAttributes($origin, $other, $currency, $currency));

    expect(balanceOf($origin, $currency))->toBe(75.0)
        ->and(balanceOf($destination, $currency))->toBe(0.0)
        ->and(balanceOf($other, $currency))->toBe(35.0);
});

it('moves the balances across currencies when a side changes currency', function (): void {
    [$team, $origin, $destination, $currency] = transferSides();
    $otherCurrency = Currency::factory()->fiat()->create(['decimal_places' => 0]);
    $destination->currencies()->attach($otherCurrency, ['balance' => '5']);
    $transfer = (new CreateTransfer)
        ->for($team)
        ->using(transferAttributes($origin, $destination, $currency, $currency));

    $updated = (new UpdateTransfer)
        ->for($team)
        ->using($transfer, transferAttributes($origin, $destination, $currency, $otherCurrency, [
            'amount_received' => '900',
            'destination_quote' => '36',
        ]));

    $updated->load('transactions');

    expect(balanceOf($destination, $currency))->toBe(0.0)
        ->and(balanceOf($destination, $otherCurrency))->toBe(905.0)
        ->and($updated->destinationLeg()?->decimal_places)->toBe(0)
        ->and((float) $updated->destinationLeg()?->quote)->toBe(36.0);
});

it('adds a fee that was not charged before', function (): void {
    [$team, $origin, $destination, $currency] = transferSides();
    $transfer = (new CreateTransfer)
        ->for($team)
        ->using(transferAttributes($origin, $destination, $currency, $currency));

    $updated = (new UpdateTransfer)
        ->for($team)
        ->using($transfer, transferAttributes($origin, $destination, $currency, $currency, ['origin_fee' => '3.00']));

    $updated->load('transactions');

    expect(balanceOf($origin, $currency))->toBe(72.0)
        ->and($updated->transactions)->toHaveCount(3)
        ->and((float) $updated->originFee()?->amount)->toBe(3.0);
});

it('removes a fee that is no longer charged', function (): void {
    [$team, $origin, $destination, $currency] = transferSides();
    $transfer = (new CreateTransfer)
        ->for($team)
        ->using(transferAttributes($origin, $destination, $currency, $currency, [
            'destination_fee' => '2.00',
            'origin_fee' => '3.00',
        ]));

    $updated = (new UpdateTransfer)
        ->for($team)
        ->using($transfer, transferAttributes($origin, $destination, $currency, $currency));

    $updated->load('transactions');

    expect(balanceOf($origin, $currency))->toBe(75.0)
        ->and(balanceOf($destination, $currency))->toBe(25.0)
        ->and($updated->transactions)->toHaveCount(2);
});

it('keeps the user that recorded the transfer', function (): void {
    [$team, $origin, $destination, $currency] = transferSides();
    $user = User::factory()->create();
    $transfer = (new CreateTransfer)
        ->for($team)
        ->by($user)
        ->using(transferAttributes($origin, $destination, $currency, $currency));

    $updated = (new UpdateTransfer)
        ->for($team)
        ->using($transfer, transferAttributes($origin, $destination, $currency, $currency, ['amount_sent' => '30.00']));

    expect($updated->user?->is($user))->toBeTrue();
});

it('syncs the tag set on the transfer and every owned transaction', function (): void {
    [$team, $origin, $destination, $currency] = transferSides();
    $existingTag = Tag::factory()->active()->for($team)->create();
    $newTag = Tag::factory()->active()->for($team)->create();
    $transfer = (new CreateTransfer)
        ->for($team)
        ->using(transferAttributes($origin, $destination, $currency, $currency, ['origin_fee' => '1.00']), [$existingTag->getKey()]);

    $updated = (new UpdateTransfer)
        ->for($team)
        ->using($transfer, transferAttributes($origin, $destination, $currency, $currency, ['origin_fee' => '1.00']), [$newTag->getKey()]);

    $updated->load('transactions');

    expect($updated->tags()->pluck($newTag->qualifyColumn($newTag->getKeyName()))->all())->toBe([$newTag->getKey()]);

    foreach ($updated->transactions as $transaction) {
        expect($transaction->tags()->pluck($newTag->qualifyColumn($newTag->getKeyName()))->all())->toBe([$newTag->getKey()]);
    }
});

it('throws when a tag belongs to another team', function (): void {
    [$team, $origin, $destination, $currency] = transferSides();
    $tag = Tag::factory()->active()->for(Team::factory())->create();
    $transfer = (new CreateTransfer)
        ->for($team)
        ->using(transferAttributes($origin, $destination, $currency, $currency));

    (new UpdateTransfer)
        ->for($team)
        ->using($transfer, transferAttributes($origin, $destination, $currency, $currency), [$tag->getKey()]);
})->throws(\InvalidArgumentException::class);

it('refuses an account belonging to another team', function (): void {
    [$team, $origin, $destination, $currency] = transferSides();
    $otherAccount = Account::factory()->bank()->for(Team::factory())->create();
    $transfer = (new CreateTransfer)
        ->for($team)
        ->using(transferAttributes($origin, $destination, $currency, $currency));

    (new UpdateTransfer)
        ->for($team)
        ->using($transfer, transferAttributes($origin, $otherAccount, $currency, $currency));
})->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
