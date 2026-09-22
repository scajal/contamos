<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\Transactions\Pages\CreateTransaction;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Catalog\Models\Currency\Quote;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Team\Resources\Transactions\Pages\CreateTransaction.
 *
 * The page writes through \App\Modules\Core\Finance\Actions\Team\CreateTransaction,
 * so the balance the entry moves is applied with it. A plain Filament
 * create would leave the account holding a stale balance.
 */
it('records the transaction and moves the account balance', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
    $account = Account::factory()->for($team)->active()->create();
    $account->currencies()->attach($currency, ['balance' => '0']);

    Livewire::test(CreateTransaction::class)
        ->fillForm([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'type' => TransactionType::Income->value,
            'amount' => '100',
            'quote' => '1',
            'occurred_at' => CarbonImmutable::now()->toDateTimeString(),
            'description' => 'Sueldo',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $transaction = Transaction::query()->where('description', 'Sueldo')->sole();

    expect($transaction->team_id)->toBe($team->getKey())
        ->and((float) $account->currencies()->where('currencies.id', $currency->getKey())->sole()->pivot?->balance)->toBe(100.0);
});

it('falls back to the catalog quote when the rate is left empty', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
    Quote::factory()->for($currency)->create([
        'observed_at' => CarbonImmutable::parse('2024-01-01 00:00:00'),
        'rate' => '1.25',
    ]);
    $account = Account::factory()->for($team)->active()->create();
    $account->currencies()->attach($currency, ['balance' => '0']);
    $tag = Tag::factory()->for($team)->create();

    Livewire::test(CreateTransaction::class)
        ->fillForm([
            'account_id' => $account->getKey(),
            'amount' => '100',
            'currency_id' => $currency->getKey(),
            'description' => 'Sueldo',
            'occurred_at' => '2024-06-01 12:00:00',
            'quote' => '',
            'tag_ids' => [$tag->getKey()],
            'type' => TransactionType::Income->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $transaction = Transaction::query()->where('description', 'Sueldo')->sole();

    expect((float) $transaction->quote)->toBe(1.25)
        ->and($transaction->tags->modelKeys())->toBe([$tag->getKey()]);
});

it('looks up the catalog quote by the selected currency key', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
    Quote::factory()->for($currency)->create([
        'observed_at' => CarbonImmutable::parse('2024-01-01 00:00:00'),
        'rate' => '1.5',
    ]);
    $account = Account::factory()->for($team)->active()->create();
    $account->currencies()->attach($currency, ['balance' => '0']);

    $page = Livewire::test(CreateTransaction::class)->instance();
    $resolveQuote = new ReflectionMethod($page, 'resolveQuote');

    expect((float) $resolveQuote->invoke($page, [
        'currency_id' => $currency->getKey(),
        'occurred_at' => '2024-06-01 12:00:00',
        'quote' => 'not-a-rate',
    ]))->toBe(1.5);
});

it('skips the catalog lookup when the currency key is not a string', function (): void {
    actingOperatorOnTeamPanel();

    $page = Livewire::test(CreateTransaction::class)->instance();
    $resolveQuote = new ReflectionMethod($page, 'resolveQuote');

    expect($resolveQuote->invoke($page, [
        'currency_id' => 1,
        'occurred_at' => '2024-06-01 12:00:00',
        'quote' => 'not-a-rate',
    ]))->toBeNull();
});
