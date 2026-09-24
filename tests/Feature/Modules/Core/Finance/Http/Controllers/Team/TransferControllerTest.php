<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Catalog\Models\Currency\Quote;
use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetAlertKind;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Finance\Models\Team\Transfer;
use App\Modules\Core\Finance\Notifications\Team\BudgetAlertNotification;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
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
 * @return array{0: \App\Modules\Core\Identity\Models\Team, 1: \App\Modules\Core\Finance\Models\Team\Account, 2: \App\Modules\Core\Finance\Models\Team\Account, 3: \App\Modules\Core\Catalog\Models\Currency, 4: \App\Modules\Core\Identity\Models\User}
 */
function actingTransferMember(): array
{
    [$user, $team] = actingMember();
    $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
    $origin = Account::factory()->bank()->active()->for($team)->create();
    $destination = Account::factory()->cash()->active()->for($team)->create();
    $origin->currencies()->attach($currency, ['balance' => '100.00']);
    $destination->currencies()->attach($currency, ['balance' => '0.00']);

    return [$team, $origin, $destination, $currency, $user];
}

/**
 * Store a \App\Modules\Core\Finance\Models\Team\Transfer through the controller
 * and return it with the transactions it owns loaded.
 *
 * @param  array<string, mixed>  $overrides
 */
function storeTransfer(Team $team, Account $origin, Account $destination, Currency $originCurrency, Currency $destinationCurrency, array $overrides = []): Transfer
{
    test()->post(
        route('finance.teams.transfers.store', ['team' => $team->getRouteKey()]),
        transferPayload($origin, $destination, $originCurrency, $destinationCurrency, $overrides),
    )->assertRedirect();

    return Transfer::query()->sole()->load('transactions');
}

/**
 * Put the team on Pro with a running budget of 10 on Other,
 * the category a transfer fee is read under.
 */
function budgetTransferFees(Team $team, Currency $currency): BudgetPeriod
{
    subscribeToPro($team);

    return BudgetPeriod::factory()->forCategory(Category::Other)->running()->for($team)->create([
        'amount' => '10',
        $currency->getForeignKey() => $currency->getKey(),
    ]);
}

describe('create', function (): void {
    it('renders the create transfer modal with the active accounts and tags', function (): void {
        [$team, , , $currency] = actingTransferMember();
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
    it('offers to undo the transfer it just recorded', function (): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transfers.store', ['team' => $team->getRouteKey()]), transferPayload($origin, $destination, $currency, $currency))
            ->assertRedirect();

        $transfer = Transfer::query()->sole();

        expect(flashedToastAction())->toBe([
            'label' => trans('Undo'),
            'method' => 'delete',
            'url' => route('finance.teams.transfers.destroy', [
                'team' => $team->getRouteKey(),
                'transfer' => $transfer->getRouteKey(),
            ]),
        ]);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->delete(flashedToastAction()['url'])
            ->assertRedirect();

        expect(Transfer::query()->count())->toBe(0)
            ->and(Transaction::query()->count())->toBe(0);
    });

    it('records both legs written with a comma decimal separator', function (): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transfers.store', ['team' => $team->getRouteKey()]), transferPayload($origin, $destination, $currency, $currency, [
                'amount_received' => '1.234,50',
                'amount_sent' => '1.234,50',
                'origin_quote' => '40,5',
            ]))
            ->assertRedirect();

        $transfer = Transfer::query()->sole()->load('transactions');

        expect((float) $transfer->originLeg()?->amount)->toBe(1234.50)
            ->and((float) $transfer->destinationLeg()?->amount)->toBe(1234.50)
            ->and((float) $transfer->originLeg()?->quote)->toBe(40.5);
    });

    it('creates the transfer with both legs and their type, account, currency, amount, decimal places, and quote', function (): void {
        [$team, $origin, $destination, $currency, $user] = actingTransferMember();
        $tag = Tag::factory()->active()->for($team)->create();

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transfers.store', ['team' => $team->getRouteKey()]), transferPayload($origin, $destination, $currency, $currency, [
                'amount_received' => '24.00',
                'origin_quote' => '2',
                'tag_ids' => [$tag->getKey()],
            ]))
            ->assertRedirect();

        assertFlashedToast(trans('Saved'));

        $transfer = Transfer::query()->sole()->load('transactions');

        expect($transfer->description)->toBe('Rent savings')
            ->and($transfer->occurred_at->toDateTimeString())->toBe('2024-06-01 12:00:00')
            ->and($transfer->user?->is($user))->toBeTrue()
            ->and($transfer->transactions)->toHaveCount(2)
            ->and($transfer->originLeg()?->type)->toBe(TransactionType::TransferOut)
            ->and($transfer->originLeg()?->account_id)->toBe($origin->getKey())
            ->and($transfer->originLeg()?->currency_id)->toBe($currency->getKey())
            ->and((float) $transfer->originLeg()?->amount)->toBe(25.0)
            ->and($transfer->originLeg()?->decimal_places)->toBe(2)
            ->and((float) $transfer->originLeg()?->quote)->toBe(2.0)
            ->and($transfer->originLeg()?->description)->toBe('Rent savings')
            ->and($transfer->originLeg()?->occurred_at->toDateTimeString())->toBe('2024-06-01 12:00:00')
            ->and($transfer->originLeg()?->user_id)->toBe($user->getKey())
            ->and($transfer->destinationLeg()?->type)->toBe(TransactionType::TransferIn)
            ->and($transfer->destinationLeg()?->account_id)->toBe($destination->getKey())
            ->and((float) $transfer->destinationLeg()?->amount)->toBe(24.0)
            ->and((float) $transfer->destinationLeg()?->quote)->toBe(1.0)
            ->and($transfer->tags()->pluck($tag->qualifyColumn($tag->getKeyName()))->all())->toBe([$tag->getKey()])
            ->and($transfer->originLeg()?->tags()->pluck($tag->qualifyColumn($tag->getKeyName()))->all())->toBe([$tag->getKey()])
            ->and($transfer->destinationLeg()?->tags()->pluck($tag->qualifyColumn($tag->getKeyName()))->all())->toBe([$tag->getKey()]);
    });

    it('applies the balances across the fee combinations', function (?string $originFee, ?string $destinationFee, float $originBalance, float $destinationBalance): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();

        storeTransfer($team, $origin, $destination, $currency, $currency, [
            'destination_fee' => $destinationFee,
            'origin_fee' => $originFee,
        ]);

        expect(balanceOf($origin, $currency))->toBe($originBalance)
            ->and(balanceOf($destination, $currency))->toBe($destinationBalance);
    })->with([
        'no fees' => [null, null, 75.0, 25.0],
        'origin fee only' => ['1.50', null, 73.5, 25.0],
        'destination fee only' => [null, '2.00', 75.0, 23.0],
        'both fees' => ['1.50', '2.00', 73.5, 23.0],
    ]);

    it('applies the balances of a cross currency transfer with fees on both sides', function (): void {
        [$team, $origin, $destination] = actingTransferMember();
        $originCurrency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $destinationCurrency = Currency::factory()->active()->fiat()->create(['decimal_places' => 0]);
        $origin->currencies()->attach($originCurrency, ['balance' => '500.00']);
        $destination->currencies()->attach($destinationCurrency, ['balance' => '0']);

        $transfer = storeTransfer($team, $origin, $destination, $originCurrency, $destinationCurrency, [
            'amount_received' => '4000',
            'amount_sent' => '100.00',
            'destination_fee' => '50',
            'destination_quote' => '40',
            'origin_fee' => '1.25',
        ]);

        expect(balanceOf($origin, $originCurrency))->toBe(398.75)
            ->and(balanceOf($destination, $destinationCurrency))->toBe(3950.0)
            ->and($transfer->destinationLeg()?->decimal_places)->toBe(0)
            ->and($transfer->destinationFee()?->decimal_places)->toBe(0);
    });

    it('creates the fee rows as expenses on their side with the translated description and the transfer tags', function (): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();
        $tag = Tag::factory()->active()->for($team)->create();

        $transfer = storeTransfer($team, $origin, $destination, $currency, $currency, [
            'destination_fee' => '2.00',
            'origin_fee' => '1.50',
            'tag_ids' => [$tag->getKey()],
        ]);

        expect($transfer->transactions)->toHaveCount(4)
            ->and($transfer->originFee()?->type)->toBe(TransactionType::Expense)
            ->and($transfer->originFee()?->description)->toBe(trans('Transfer fee'))
            ->and($transfer->originFee()?->account_id)->toBe($origin->getKey())
            ->and((float) $transfer->originFee()?->amount)->toBe(1.5)
            ->and($transfer->originFee()?->tags()->pluck($tag->qualifyColumn($tag->getKeyName()))->all())->toBe([$tag->getKey()])
            ->and($transfer->destinationFee()?->type)->toBe(TransactionType::Expense)
            ->and($transfer->destinationFee()?->account_id)->toBe($destination->getKey())
            ->and((float) $transfer->destinationFee()?->amount)->toBe(2.0)
            ->and($transfer->destinationFee()?->tags()->pluck($tag->qualifyColumn($tag->getKeyName()))->all())->toBe([$tag->getKey()]);
    });

    it('converts a datetime-local occurred_at to UTC using the submitted timezone', function (): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();

        $transfer = storeTransfer($team, $origin, $destination, $currency, $currency, [
            'occurred_at' => '2026-09-06T12:21',
            'timezone' => 'America/Argentina/Buenos_Aires',
        ]);

        expect($transfer->occurred_at->toDateTimeString())->toBe('2026-09-06 15:21:00')
            ->and($transfer->originLeg()?->occurred_at->toDateTimeString())->toBe('2026-09-06 15:21:00');
    });

    it('defaults each blank quote from its own currency rate as of occurred_at', function (): void {
        [$team, $origin, $destination] = actingTransferMember();
        $originCurrency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $destinationCurrency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $origin->currencies()->attach($originCurrency, ['balance' => '500.00']);
        $destination->currencies()->attach($destinationCurrency, ['balance' => '0.00']);
        Quote::factory()->for($originCurrency)->create(['observed_at' => '2024-06-01 00:00:00', 'rate' => '1.5']);
        Quote::factory()->for($originCurrency)->create(['observed_at' => '2024-06-15 00:00:00', 'rate' => '9']);
        Quote::factory()->for($destinationCurrency)->create(['observed_at' => '2024-06-01 00:00:00', 'rate' => '40']);

        $payload = transferPayload($origin, $destination, $originCurrency, $destinationCurrency);
        unset($payload['destination_quote'], $payload['origin_quote']);

        $this->post(route('finance.teams.transfers.store', ['team' => $team->getRouteKey()]), $payload)
            ->assertRedirect();

        $transfer = Transfer::query()->sole()->load('transactions');

        expect($transfer->originLeg()?->quote)->toBe('1.500000000000000000')
            ->and($transfer->destinationLeg()?->quote)->toBe('40.000000000000000000');
    });

    it('rejects a blank quote when no rate exists as of occurred_at', function (): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();
        Quote::factory()->for($currency)->create(['observed_at' => '2024-06-15 00:00:00', 'rate' => '2']);

        $payload = transferPayload($origin, $destination, $currency, $currency, ['occurred_at' => '2024-01-01 12:00:00']);
        unset($payload['destination_quote'], $payload['origin_quote']);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transfers.store', ['team' => $team->getRouteKey()]), $payload)
            ->assertInvalid(['destination_quote', 'origin_quote']);
    });

    it('keeps a submitted zero quote instead of falling back to the as-of rate', function (): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();
        Quote::factory()->for($currency)->create(['observed_at' => '2024-06-01 00:00:00', 'rate' => '1.5']);

        $transfer = storeTransfer($team, $origin, $destination, $currency, $currency, [
            'destination_quote' => '0',
            'origin_quote' => '0',
        ]);

        expect($transfer->originLeg()?->quote)->toBe('0.000000000000000000')
            ->and($transfer->destinationLeg()?->quote)->toBe('0.000000000000000000');
    });

    it('keeps the submitted quotes instead of the as-of rates', function (): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();
        Quote::factory()->for($currency)->create(['observed_at' => '2024-06-01 00:00:00', 'rate' => '1.5']);

        $transfer = storeTransfer($team, $origin, $destination, $currency, $currency, [
            'destination_quote' => '38.5',
            'origin_quote' => '41.25',
        ]);

        expect($transfer->originLeg()?->quote)->toBe('41.250000000000000000')
            ->and($transfer->destinationLeg()?->quote)->toBe('38.500000000000000000');
    });

    it('rejects a transfer to the same account', function (): void {
        [$team, $origin, , $currency] = actingTransferMember();

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transfers.store', ['team' => $team->getRouteKey()]), transferPayload($origin, $origin, $currency, $currency))
            ->assertInvalid(['destination_account_id']);
    });

    it('rejects an inactive account', function (): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();
        $destination->deactivate();

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transfers.store', ['team' => $team->getRouteKey()]), transferPayload($origin, $destination, $currency, $currency))
            ->assertInvalid(['destination_account_id']);
    });

    it('rejects an inactive currency', function (): void {
        [$team, $origin, $destination] = actingTransferMember();
        $inactive = Currency::factory()->inactive()->fiat()->create(['decimal_places' => 2]);
        $origin->currencies()->attach($inactive, ['balance' => '10.00']);
        $destination->currencies()->attach($inactive, ['balance' => '0.00']);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transfers.store', ['team' => $team->getRouteKey()]), transferPayload($origin, $destination, $inactive, $inactive))
            ->assertInvalid(['destination_currency_id', 'origin_currency_id']);
    });

    it('rejects a currency that is not attached to its account', function (): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();
        $other = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $origin->currencies()->attach($other, ['balance' => '10.00']);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transfers.store', ['team' => $team->getRouteKey()]), transferPayload($origin, $destination, $other, $other))
            ->assertInvalid(['destination_currency_id']);
    });

    it('rejects a non-positive amount or fee', function (string $field, string $value): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transfers.store', ['team' => $team->getRouteKey()]), transferPayload($origin, $destination, $currency, $currency, [$field => $value]))
            ->assertInvalid([$field]);
    })->with([
        'zero amount sent' => ['amount_sent', '0'],
        'negative amount sent' => ['amount_sent', '-5.00'],
        'zero amount received' => ['amount_received', '0.00'],
        'negative amount received' => ['amount_received', '-5.00'],
        'zero origin fee' => ['origin_fee', '0'],
        'negative origin fee' => ['origin_fee', '-1.00'],
        'zero destination fee' => ['destination_fee', '0'],
        'negative destination fee' => ['destination_fee', '-1.00'],
    ]);

    it('rejects an account of another team', function (): void {
        [$team, $origin, , $currency] = actingTransferMember();
        $other = Account::factory()->bank()->active()->for(Team::factory())->create();
        $other->currencies()->attach($currency);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transfers.store', ['team' => $team->getRouteKey()]), transferPayload($origin, $other, $currency, $currency))
            ->assertInvalid(['destination_account_id']);
    });

    it('rejects a tag of another team', function (): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();
        $foreign = Tag::factory()->active()->for(Team::factory())->create();

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transfers.store', ['team' => $team->getRouteKey()]), transferPayload($origin, $destination, $currency, $currency, [
                'tag_ids' => [$foreign->getKey()],
            ]))
            ->assertInvalid(['tag_ids.0']);
    });

    it('rejects an inactive tag', function (): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();
        $inactive = Tag::factory()->inactive()->for($team)->create();

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transfers.store', ['team' => $team->getRouteKey()]), transferPayload($origin, $destination, $currency, $currency, [
                'tag_ids' => [$inactive->getKey()],
            ]))
            ->assertInvalid(['tag_ids.0']);
    });

    it('rejects duplicate tag ids', function (): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();
        $tag = Tag::factory()->active()->for($team)->create();

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transfers.store', ['team' => $team->getRouteKey()]), transferPayload($origin, $destination, $currency, $currency, [
                'tag_ids' => [$tag->getKey(), $tag->getKey()],
            ]))
            ->assertInvalid(['tag_ids.0']);
    });

    it('rejects an empty payload', function (): void {
        [$team] = actingTransferMember();

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transfers.store', ['team' => $team->getRouteKey()]), [])
            ->assertInvalid(['amount_received', 'amount_sent', 'destination_account_id', 'destination_currency_id', 'destination_quote', 'occurred_at', 'origin_account_id', 'origin_currency_id', 'origin_quote']);
    });

    it('records the transfer and its legs as one entry in the activity log', function (): void {
        [$team, $origin, $destination, $currency, $user] = actingTransferMember();
        clearActivities();

        $transfer = storeTransfer($team, $origin, $destination, $currency, $currency);

        $activities = activitiesOf($team);

        expect($activities->pluck('event')->unique()->all())->toBe(['created'])
            ->and($activities)->toHaveCount(3)
            ->and($activities->pluck('batch_uuid')->unique()->filter()->all())->toHaveCount(1)
            ->and($activities->pluck('causer_id')->unique()->all())->toBe([$user->getKey()])
            ->and($activities->first()?->subject?->is($transfer))->toBeTrue()
            ->and($activities->first()?->attribute_changes['attributes']['description'])->toBe('Rent savings');
    });

    it('alerts when its fee pushes a budget over', function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-09-15 12:00:00'));
        [$team, $origin, $destination, $currency, $user] = actingTransferMember();
        $budget = budgetTransferFees($team, $currency);
        Notification::fake();

        storeTransfer($team, $origin, $destination, $currency, $currency, [
            'occurred_at' => '2026-09-15 10:00:00',
            'origin_fee' => '15.00',
        ]);

        Notification::assertSentTo($user, BudgetAlertNotification::class, fn (BudgetAlertNotification $notification): bool => $notification->budget->is($budget)
            && $notification->kind === BudgetAlertKind::Over);
    });
});

describe('show', function (): void {
    it('renders the transfer modal with both legs and its fees', function (): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();
        $transfer = storeTransfer($team, $origin, $destination, $currency, $currency, [
            'destination_fee' => '2.00',
            'origin_fee' => '1.50',
        ]);

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.transfers.show', ['team' => $team->getRouteKey(), 'transfer' => $transfer->getKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('finance/teams/transfers/show')
                ->where('transfer.id', $transfer->getKey())
                ->where('transfer.origin_leg_id', $transfer->originLeg()?->getKey())
                ->where('transfer.destination_leg_id', $transfer->destinationLeg()?->getKey())
                ->where('transfer.origin_fee_id', $transfer->originFee()?->getKey())
                ->where('transfer.destination_fee_id', $transfer->destinationFee()?->getKey())
                ->has('transfer.transactions', 4)
                ->has('transfer.transactions.0.account')
                ->has('transfer.transactions.0.currency')
                ->has('transfer.user'));
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
        $tag = Tag::factory()->active()->for($team)->create();
        $transfer = storeTransfer($team, $origin, $destination, $currency, $currency, ['tag_ids' => [$tag->getKey()]]);

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.transfers.edit', ['team' => $team->getRouteKey(), 'transfer' => $transfer->getKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('finance/teams/transfers/edit')
                ->has('accounts', 2)
                ->has('accounts.0.currencies.0.quotes')
                ->has('tags', 1)
                ->where('tags.0.id', $tag->getKey())
                ->where('transfer.id', $transfer->getKey())
                ->has('transfer.transactions', 2));
    });

    it('includes the inactive current accounts, currencies, and attached tags', function (): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();
        $tag = Tag::factory()->active()->for($team)->create();
        $transfer = storeTransfer($team, $origin, $destination, $currency, $currency, ['tag_ids' => [$tag->getKey()]]);
        $origin->deactivate();
        $destination->deactivate();
        $currency->deactivate();
        $tag->deactivate();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.transfers.edit', ['team' => $team->getRouteKey(), 'transfer' => $transfer->getKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->has('accounts', 2)
                ->has('accounts.0.currencies', 1)
                ->where('accounts.0.currencies.0.id', $currency->getKey())
                ->has('tags', 1)
                ->where('tags.0.id', $tag->getKey()));
    });

    it('returns 404 for a transfer of another team', function (): void {
        [$team] = actingTransferMember();
        $transfer = Transfer::factory()->for(Team::factory())->create();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.transfers.edit', ['team' => $team->getRouteKey(), 'transfer' => $transfer->getKey()]))
            ->assertNotFound();
    });
});

describe('update', function (): void {
    it('changes every field, keeps the leg ids and the creator, and moves the balances', function (): void {
        [$team, $origin, $destination, $currency, $user] = actingTransferMember();
        $transfer = storeTransfer($team, $origin, $destination, $currency, $currency);
        $originLegId = $transfer->originLeg()?->getKey();
        $destinationLegId = $transfer->destinationLeg()?->getKey();

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.transfers.update', ['team' => $team->getRouteKey(), 'transfer' => $transfer->getKey()]), transferPayload($origin, $destination, $currency, $currency, [
                'amount_received' => '40.00',
                'amount_sent' => '40.00',
                'description' => 'Moved more',
                'occurred_at' => '2024-07-02 09:30:00',
            ]))
            ->assertRedirect();

        assertFlashedToast(trans('Saved'));

        $transfer = $transfer->fresh()?->load('transactions');

        expect($transfer?->description)->toBe('Moved more')
            ->and($transfer?->occurred_at->toDateTimeString())->toBe('2024-07-02 09:30:00')
            ->and($transfer?->user?->is($user))->toBeTrue()
            ->and($transfer?->originLeg()?->getKey())->toBe($originLegId)
            ->and($transfer?->destinationLeg()?->getKey())->toBe($destinationLegId)
            ->and($transfer?->originLeg()?->occurred_at->toDateTimeString())->toBe('2024-07-02 09:30:00')
            ->and($transfer?->originLeg()?->description)->toBe('Moved more')
            ->and(balanceOf($origin, $currency))->toBe(60.0)
            ->and(balanceOf($destination, $currency))->toBe(40.0);
    });

    it('leaves the description alone when the field is omitted', function (): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();
        $transfer = storeTransfer($team, $origin, $destination, $currency, $currency);

        $payload = transferPayload($origin, $destination, $currency, $currency, ['amount_sent' => '30.00']);
        unset($payload['description']);

        $this->patch(route('finance.teams.transfers.update', ['team' => $team->getRouteKey(), 'transfer' => $transfer->getKey()]), $payload)
            ->assertRedirect();

        $transfer = $transfer->fresh()?->load('transactions');

        expect($transfer?->description)->toBe('Rent savings')
            ->and($transfer?->originLeg()?->description)->toBe('Rent savings');
    });

    it('clears the description when the field is submitted empty', function (): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();
        $transfer = storeTransfer($team, $origin, $destination, $currency, $currency);

        $this->patch(route('finance.teams.transfers.update', ['team' => $team->getRouteKey(), 'transfer' => $transfer->getKey()]), transferPayload($origin, $destination, $currency, $currency, [
            'description' => null,
        ]))->assertRedirect();

        $transfer = $transfer->fresh()?->load('transactions');

        expect($transfer?->description)->toBeNull()
            ->and($transfer?->originLeg()?->description)->toBeNull();
    });

    it('moves the balances across accounts when a side changes account', function (): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();
        $other = Account::factory()->bank()->active()->for($team)->create();
        $other->currencies()->attach($currency, ['balance' => '10.00']);
        $transfer = storeTransfer($team, $origin, $destination, $currency, $currency);

        $this->patch(route('finance.teams.transfers.update', ['team' => $team->getRouteKey(), 'transfer' => $transfer->getKey()]), transferPayload($origin, $other, $currency, $currency))
            ->assertRedirect();

        expect(balanceOf($origin, $currency))->toBe(75.0)
            ->and(balanceOf($destination, $currency))->toBe(0.0)
            ->and(balanceOf($other, $currency))->toBe(35.0);
    });

    it('moves the balances across currencies when a side changes currency', function (): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();
        $other = Currency::factory()->active()->fiat()->create(['decimal_places' => 0]);
        $destination->currencies()->attach($other, ['balance' => '5']);
        $transfer = storeTransfer($team, $origin, $destination, $currency, $currency);

        $this->patch(route('finance.teams.transfers.update', ['team' => $team->getRouteKey(), 'transfer' => $transfer->getKey()]), transferPayload($origin, $destination, $currency, $other, [
            'amount_received' => '900',
            'destination_quote' => '36',
        ]))->assertRedirect();

        $transfer = $transfer->fresh()?->load('transactions');

        expect(balanceOf($destination, $currency))->toBe(0.0)
            ->and(balanceOf($destination, $other))->toBe(905.0)
            ->and($transfer?->destinationLeg()?->decimal_places)->toBe(0);
    });

    it('adds a fee that was not charged before', function (): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();
        $transfer = storeTransfer($team, $origin, $destination, $currency, $currency);

        $this->patch(route('finance.teams.transfers.update', ['team' => $team->getRouteKey(), 'transfer' => $transfer->getKey()]), transferPayload($origin, $destination, $currency, $currency, [
            'origin_fee' => '3.00',
        ]))->assertRedirect();

        $transfer = $transfer->fresh()?->load('transactions');

        expect($transfer?->transactions)->toHaveCount(3)
            ->and((float) $transfer?->originFee()?->amount)->toBe(3.0)
            ->and(balanceOf($origin, $currency))->toBe(72.0);
    });

    it('removes a fee that is no longer charged', function (): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();
        $transfer = storeTransfer($team, $origin, $destination, $currency, $currency, [
            'destination_fee' => '2.00',
            'origin_fee' => '3.00',
        ]);

        $this->patch(route('finance.teams.transfers.update', ['team' => $team->getRouteKey(), 'transfer' => $transfer->getKey()]), transferPayload($origin, $destination, $currency, $currency))
            ->assertRedirect();

        $transfer = $transfer->fresh()?->load('transactions');

        expect($transfer?->transactions)->toHaveCount(2)
            ->and(balanceOf($origin, $currency))->toBe(75.0)
            ->and(balanceOf($destination, $currency))->toBe(25.0);
    });

    it('keeps the stored quotes when the date and the currencies are unchanged', function (): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();
        Quote::factory()->for($currency)->create(['observed_at' => '2024-06-01 00:00:00', 'rate' => '1.5']);
        $transfer = storeTransfer($team, $origin, $destination, $currency, $currency, [
            'destination_quote' => '38.5',
            'origin_quote' => '41.25',
        ]);

        $this->patch(route('finance.teams.transfers.update', ['team' => $team->getRouteKey(), 'transfer' => $transfer->getKey()]), transferPayload($origin, $destination, $currency, $currency, [
            'destination_quote' => '38.5',
            'origin_quote' => '41.25',
        ]))->assertRedirect();

        $transfer = $transfer->fresh()?->load('transactions');

        expect($transfer?->originLeg()?->quote)->toBe('41.250000000000000000')
            ->and($transfer?->destinationLeg()?->quote)->toBe('38.500000000000000000');
    });

    it('syncs the tag set on the transfer and every owned transaction', function (): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();
        $existing = Tag::factory()->active()->for($team)->create();
        $replacement = Tag::factory()->active()->for($team)->create();
        $transfer = storeTransfer($team, $origin, $destination, $currency, $currency, [
            'origin_fee' => '1.00',
            'tag_ids' => [$existing->getKey()],
        ]);

        $this->patch(route('finance.teams.transfers.update', ['team' => $team->getRouteKey(), 'transfer' => $transfer->getKey()]), transferPayload($origin, $destination, $currency, $currency, [
            'origin_fee' => '1.00',
            'tag_ids' => [$replacement->getKey()],
        ]))->assertRedirect();

        $transfer = $transfer->fresh()?->load('transactions');

        expect($transfer?->tags()->pluck($replacement->qualifyColumn($replacement->getKeyName()))->all())->toBe([$replacement->getKey()]);

        foreach ($transfer?->transactions ?? [] as $transaction) {
            expect($transaction->tags()->pluck($replacement->qualifyColumn($replacement->getKeyName()))->all())->toBe([$replacement->getKey()]);
        }
    });

    it('accepts the inactive accounts, currencies, and tags the transfer already points at', function (): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();
        $tag = Tag::factory()->active()->for($team)->create();
        $transfer = storeTransfer($team, $origin, $destination, $currency, $currency, ['tag_ids' => [$tag->getKey()]]);
        $origin->deactivate();
        $currency->deactivate();
        $tag->deactivate();

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.transfers.update', ['team' => $team->getRouteKey(), 'transfer' => $transfer->getKey()]), transferPayload($origin, $destination, $currency, $currency, [
                'amount_sent' => '30.00',
                'tag_ids' => [$tag->getKey()],
            ]))
            ->assertValid()
            ->assertRedirect();

        expect(balanceOf($origin, $currency))->toBe(70.0);
    });

    it('returns 404 for a transfer of another team', function (): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();
        $transfer = Transfer::factory()->for(Team::factory())->create();

        $this->patch(route('finance.teams.transfers.update', ['team' => $team->getRouteKey(), 'transfer' => $transfer->getKey()]), transferPayload($origin, $destination, $currency, $currency))
            ->assertNotFound();
    });

    it('alerts when an edit adds a fee that pushes a budget over', function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-09-15 12:00:00'));
        [$team, $origin, $destination, $currency, $user] = actingTransferMember();
        budgetTransferFees($team, $currency);
        $transfer = storeTransfer($team, $origin, $destination, $currency, $currency, ['occurred_at' => '2026-09-15 10:00:00']);
        Notification::fake();

        $this->patch(route('finance.teams.transfers.update', ['team' => $team->getRouteKey(), 'transfer' => $transfer->getKey()]), transferPayload($origin, $destination, $currency, $currency, [
            'occurred_at' => '2026-09-15 10:00:00',
            'origin_fee' => '15.00',
        ]))->assertRedirect();

        Notification::assertSentTo($user, BudgetAlertNotification::class, fn (BudgetAlertNotification $notification): bool => $notification->kind === BudgetAlertKind::Over);
    });
});

describe('destroy', function (): void {
    it('deletes the transfer with every owned row and restores the balances', function (): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();
        $transfer = storeTransfer($team, $origin, $destination, $currency, $currency, [
            'destination_fee' => '2.00',
            'origin_fee' => '1.00',
        ]);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->delete(route('finance.teams.transfers.destroy', ['team' => $team->getRouteKey(), 'transfer' => $transfer->getKey()]))
            ->assertRedirect();

        assertFlashedToast(trans('Deleted'));

        expect(Transfer::query()->count())->toBe(0)
            ->and(Transaction::query()->count())->toBe(0)
            ->and(balanceOf($origin, $currency))->toBe(100.0)
            ->and(balanceOf($destination, $currency))->toBe(0.0);
    });

    it('returns 404 for a transfer of another team', function (): void {
        [$team] = actingTransferMember();
        $transfer = Transfer::factory()->for(Team::factory())->create();

        $this->delete(route('finance.teams.transfers.destroy', ['team' => $team->getRouteKey(), 'transfer' => $transfer->getKey()]))
            ->assertNotFound();
    });

    it('records the deletion of the transfer and its legs as one entry in the activity log', function (): void {
        [$team, $origin, $destination, $currency] = actingTransferMember();
        $transfer = storeTransfer($team, $origin, $destination, $currency, $currency);
        clearActivities();

        $this->delete(route('finance.teams.transfers.destroy', [
            'team' => $team->getRouteKey(),
            'transfer' => $transfer->getKey(),
        ]))->assertRedirect();

        $activities = activitiesOf($team);

        expect($activities->pluck('event')->unique()->all())->toBe(['deleted'])
            ->and($activities)->toHaveCount(3)
            ->and($activities->pluck('batch_uuid')->unique()->filter()->all())->toHaveCount(1)
            ->and($activities->last()?->attribute_changes['old']['description'])->toBe('Rent savings');
    });
});
