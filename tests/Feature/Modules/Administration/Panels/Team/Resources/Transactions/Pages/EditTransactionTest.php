<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\Transactions\Pages\EditTransaction;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use Carbon\CarbonImmutable;
use Filament\Actions\DeleteAction;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Team\Resources\Transactions\Pages\EditTransaction.
 *
 * The page writes through \App\Modules\Core\Finance\Actions\Team\UpdateTransaction
 * and \App\Modules\Core\Finance\Actions\Team\DeleteTransaction, so the
 * balance the entry moved is reverted and applied again.
 */
it('updates the transaction and the tags it carries', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
    $account = Account::factory()->for($team)->active()->create();
    $account->currencies()->attach($currency, ['balance' => '100']);
    $tag = Tag::factory()->for($team)->create();
    $transaction = Transaction::factory()->income()->create([
        'account_id' => $account->getKey(),
        'amount' => '100',
        'currency_id' => $currency->getKey(),
        'decimal_places' => 2,
        'description' => 'Old',
        'occurred_at' => CarbonImmutable::parse('2024-01-01 12:00:00'),
        'quote' => '1',
        'team_id' => $team->getKey(),
    ]);

    Livewire::test(EditTransaction::class, ['record' => $transaction->getKey()])
        ->fillForm([
            'account_id' => $account->getKey(),
            'amount' => '50',
            'currency_id' => $currency->getKey(),
            'description' => 'Sueldo',
            'occurred_at' => CarbonImmutable::parse('2024-01-02 12:00:00')->toDateTimeString(),
            'quote' => '1',
            'tag_ids' => [$tag->getKey()],
            'type' => TransactionType::Income->value,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect($transaction->fresh()?->description)->toBe('Sueldo')
        ->and((float) $transaction->fresh()?->amount)->toBe(50.0)
        ->and($transaction->fresh()?->tags->modelKeys())->toBe([$tag->getKey()]);
});

it('deletes the transaction and reverts the balance it moved', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
    $account = Account::factory()->for($team)->active()->create();
    $account->currencies()->attach($currency, ['balance' => '100']);
    $transaction = Transaction::factory()->income()->create([
        'account_id' => $account->getKey(),
        'amount' => '100',
        'currency_id' => $currency->getKey(),
        'decimal_places' => 2,
        'quote' => '1',
        'team_id' => $team->getKey(),
    ]);

    Livewire::test(EditTransaction::class, ['record' => $transaction->getKey()])
        ->callAction(DeleteAction::class)
        ->assertNotified();

    expect(Transaction::query()->find($transaction->getKey()))->toBeNull();
});
