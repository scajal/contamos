<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Finance\Models\Team\Transfer;
use App\Modules\Core\Identity\Models\Team;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Feature tests for \App\Modules\Core\Finance\Http\Controllers\Team\TransferController.
 */

/**
 * Valid payload for storing a \App\Modules\Core\Finance\Models\Team\Transfer.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function transferPayload(Account $origin, Account $destination, Currency $originCurrency, Currency $destinationCurrency, array $overrides = []): array
{
    return [
        'amount_received' => '25.00',
        'amount_sent' => '25.00',
        'description' => 'Rent savings',
        'destination_account_id' => $destination->getKey(),
        'destination_currency_id' => $destinationCurrency->getKey(),
        'destination_quote' => '1',
        'occurred_at' => '2024-06-01 12:00:00',
        'origin_account_id' => $origin->getKey(),
        'origin_currency_id' => $originCurrency->getKey(),
        'origin_quote' => '1',
        ...$overrides,
    ];
}

/**
 * Authenticate a member of a \App\Modules\Core\Identity\Models\Team owning two
 * active accounts that share an active \App\Modules\Core\Catalog\Models\Currency.
 *
 * @return array{0: \App\Modules\Core\Identity\Models\Team, 1: \App\Modules\Core\Finance\Models\Team\Account, 2: \App\Modules\Core\Finance\Models\Team\Account, 3: \App\Modules\Core\Catalog\Models\Currency}
 */
function actingTransferMember(): array
{
    [, $team] = actingMember();
    $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
    $origin = Account::factory()->bank()->active()->for($team)->create();
    $destination = Account::factory()->cash()->active()->for($team)->create();
    $origin->currencies()->attach($currency, ['balance' => '100.00']);
    $destination->currencies()->attach($currency, ['balance' => '0.00']);

    return [$team, $origin, $destination, $currency];
}

describe('create', function (): void {
    it('renders the create transfer modal with the active accounts and tags', function (): void {
        [$team, $origin, , $currency] = actingTransferMember();
        Account::factory()->bank()->inactive()->for($team)->create();
        $tag = Tag::factory()->active()->for($team)->create();
        Tag::factory()->inactive()->for($team)->create();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.transfers.create', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('finance/teams/transfers/create')
                ->has('accounts', 2)
                ->where('accounts.0.currencies.0.id', $currency->getKey())
                ->has('tags', 1)
                ->where('tags.0.id', $tag->getKey()));
    });
});

describe('store', function (): void {
    it('creates the transfer with both legs and applies the balances', function (): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();
        $tag = Tag::factory()->active()->for($team)->create();

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transfers.store', ['team' => $team->getRouteKey()]), transferPayload($origin, $destination, $currency, $currency, [
                'tag_ids' => [$tag->getKey()],
            ]))
            ->assertRedirect();

        assertFlashedToast(trans('Saved'));

        $transfer = Transfer::query()->sole()->load('transactions');

        expect($transfer->transactions)->toHaveCount(2)
            ->and($transfer->originLeg()?->type)->toBe(TransactionType::TransferOut)
            ->and($transfer->destinationLeg()?->type)->toBe(TransactionType::TransferIn)
            ->and(balanceOf($origin, $currency))->toBe(75.0)
            ->and(balanceOf($destination, $currency))->toBe(25.0)
            ->and($transfer->tags()->pluck($tag->qualifyColumn($tag->getKeyName()))->all())->toBe([$tag->getKey()]);
    });

    it('creates the fee rows as expenses charged on their side', function (): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();

        $this->post(route('finance.teams.transfers.store', ['team' => $team->getRouteKey()]), transferPayload($origin, $destination, $currency, $currency, [
            'destination_fee' => '2.00',
            'origin_fee' => '1.50',
        ]))->assertRedirect();

        $transfer = Transfer::query()->sole()->load('transactions');

        expect($transfer->transactions)->toHaveCount(4)
            ->and($transfer->originFee()?->type)->toBe(TransactionType::Expense)
            ->and($transfer->originFee()?->description)->toBe(trans('Transfer fee'))
            ->and(balanceOf($origin, $currency))->toBe(73.5)
            ->and(balanceOf($destination, $currency))->toBe(23.0);
    });

    it('rejects a transfer to the same account', function (): void {
        [$team, $origin, , $currency] = actingTransferMember();

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transfers.store', ['team' => $team->getRouteKey()]), transferPayload($origin, $origin, $currency, $currency))
            ->assertSessionHasErrors('destination_account_id');
    });

    it('rejects an account of another team', function (): void {
        [$team, $origin, , $currency] = actingTransferMember();
        $other = Account::factory()->bank()->active()->for(Team::factory())->create();
        $other->currencies()->attach($currency);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transfers.store', ['team' => $team->getRouteKey()]), transferPayload($origin, $other, $currency, $currency))
            ->assertSessionHasErrors('destination_account_id');
    });

    it('rejects an empty payload', function (): void {
        [$team] = actingTransferMember();

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transfers.store', ['team' => $team->getRouteKey()]), [])
            ->assertSessionHasErrors(['amount_received', 'amount_sent', 'destination_account_id', 'destination_currency_id', 'destination_quote', 'occurred_at', 'origin_account_id', 'origin_currency_id', 'origin_quote']);
    });
});

describe('show', function (): void {
    it('renders the transfer modal with its legs', function (): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();
        $this->post(route('finance.teams.transfers.store', ['team' => $team->getRouteKey()]), transferPayload($origin, $destination, $currency, $currency));
        $transfer = Transfer::query()->sole();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.transfers.show', ['team' => $team->getRouteKey(), 'transfer' => $transfer->getKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('finance/teams/transfers/show')
                ->has('transfer.transactions', 2));
    });

    it('returns 404 for a transfer of another team', function (): void {
        [$team] = actingTransferMember();
        $transfer = Transfer::factory()->for(Team::factory())->create();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.transfers.show', ['team' => $team->getRouteKey(), 'transfer' => $transfer->getKey()]))
            ->assertNotFound();
    });
});

describe('edit', function (): void {
    it('renders the edit transfer modal with its current values', function (): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();
        $this->post(route('finance.teams.transfers.store', ['team' => $team->getRouteKey()]), transferPayload($origin, $destination, $currency, $currency));
        $transfer = Transfer::query()->sole();
        $origin->deactivate();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.transfers.edit', ['team' => $team->getRouteKey(), 'transfer' => $transfer->getKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('finance/teams/transfers/edit')
                ->has('accounts', 2)
                ->where('transfer.id', $transfer->getKey())
                ->has('transfer.transactions', 2));
    });
});

describe('update', function (): void {
    it('updates the transfer and moves the balances', function (): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();
        $this->post(route('finance.teams.transfers.store', ['team' => $team->getRouteKey()]), transferPayload($origin, $destination, $currency, $currency));
        $transfer = Transfer::query()->sole();

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.transfers.update', ['team' => $team->getRouteKey(), 'transfer' => $transfer->getKey()]), transferPayload($origin, $destination, $currency, $currency, [
                'amount_received' => '40.00',
                'amount_sent' => '40.00',
                'description' => 'Moved more',
            ]))
            ->assertRedirect();

        assertFlashedToast(trans('Saved'));

        expect($transfer->refresh()->description)->toBe('Moved more')
            ->and(balanceOf($origin, $currency))->toBe(60.0)
            ->and(balanceOf($destination, $currency))->toBe(40.0);
    });
});

describe('destroy', function (): void {
    it('deletes the transfer with every owned row and restores the balances', function (): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();
        $this->post(route('finance.teams.transfers.store', ['team' => $team->getRouteKey()]), transferPayload($origin, $destination, $currency, $currency, ['origin_fee' => '1.00']));
        $transfer = Transfer::query()->sole();

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->delete(route('finance.teams.transfers.destroy', ['team' => $team->getRouteKey(), 'transfer' => $transfer->getKey()]))
            ->assertRedirect();

        assertFlashedToast(trans('Deleted'));

        expect(Transfer::query()->count())->toBe(0)
            ->and(Transaction::query()->count())->toBe(0)
            ->and(balanceOf($origin, $currency))->toBe(100.0)
            ->and(balanceOf($destination, $currency))->toBe(0.0);
    });
});
