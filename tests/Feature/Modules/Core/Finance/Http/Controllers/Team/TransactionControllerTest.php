<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Catalog\Models\Currency\Quote;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Finance\Models\Team\Transfer;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Feature tests for \App\Modules\Core\Finance\Http\Controllers\Team\TransactionController.
 */

/**
 * Valid payload for storing a \App\Modules\Core\Finance\Models\Team\Transaction.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function transactionPayload(array $overrides = []): array
{
    return [
        'amount' => '10.50',
        'description' => 'Lunch',
        'occurred_at' => '2024-06-01 12:00:00',
        'quote' => '1',
        'type' => TransactionType::Expense->value,
        ...$overrides,
    ];
}

/**
 * Parse the rows of an exported CSV body, byte-order mark aside.
 *
 * @return array<int, array<int, string|null>>
 */
function csvRows(string $body): array
{
    $handle = fopen('php://memory', 'r+');

    fwrite($handle, Str::after($body, "\u{FEFF}"));
    rewind($handle);

    $rows = [];

    while (($row = fgetcsv($handle)) !== false) {
        $rows[] = $row;
    }

    fclose($handle);

    return $rows;
}

describe('index', function (): void {
    it('renders a deferred list of transactions', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);
        $transaction = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
            'description' => 'Lunch',
        ]);
        $tag = Tag::factory()->active()->for($team)->create();
        $transaction->tags()->attach($tag->getKey());

        $this->get(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('finance/teams/transactions/index')
                ->has('user')
                ->has('accounts')
                ->has('currencies')
                ->has('types')
                ->has('filters')
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('transactions.data', 1)
                    ->where('transactions.data.0.id', $transaction->getKey())
                    ->has('transactions.data.0.tags', 1)
                    ->where('transactions.data.0.tags.0.id', $tag->getKey())
                    ->where('transactions.data.0.tags.0.name', $tag->name)));
    });

    it('filters transactions by type, account, currency, search, and date range', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create();
        $otherCurrency = Currency::factory()->active()->fiat()->create();
        $account = Account::factory()->bank()->active()->for($team)->create();
        $otherAccount = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);
        $otherAccount->currencies()->attach($otherCurrency);

        // A whole month inside the history window every plan keeps,
        // so the date range is what narrows the list and not the
        // plan's cutoff.
        $month = CarbonImmutable::now()->startOfMonth()->subMonth();

        $matching = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
            'description' => 'Office lunch',
            'occurred_at' => $month->addDays(14),
        ]);
        Transaction::factory()->income()->create([
            'account_id' => $otherAccount->getKey(),
            'currency_id' => $otherCurrency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
            'description' => 'Salary',
            'occurred_at' => $month->subMonths(2),
        ]);

        $this->get(route('finance.teams.transactions.index', [
            'team' => $team->getRouteKey(),
            'filter' => [
                'type' => TransactionType::Expense->value,
                'account' => $account->getKey(),
                'currency' => $currency->getKey(),
                'search' => 'lunch',
                'from' => $month->toDateString(),
                'to' => $month->endOfMonth()->toDateString(),
            ],
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('filters.type', TransactionType::Expense->value)
                ->where('filters.account', $account->getKey())
                ->where('filters.currency', $currency->getKey())
                ->where('filters.search', 'lunch')
                ->where('filters.from', $month->toDateString())
                ->where('filters.to', $month->endOfMonth()->toDateString())
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('transactions.data', 1)
                    ->where('transactions.data.0.id', $matching->getKey())));
    });

    it('filters transactions by id through search', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create();
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);

        $matching = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
            'description' => 'Lunch',
        ]);
        Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
            'description' => 'Dinner',
        ]);

        $this->get(route('finance.teams.transactions.index', [
            'team' => $team->getRouteKey(),
            'filter' => [
                'search' => $matching->getKey(),
            ],
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('filters.search', $matching->getKey())
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('transactions.data', 1)
                    ->where('transactions.data.0.id', $matching->getKey())));
    });

    it('rejects a to date that is before from', function (): void {
        [$user, $team] = actingMember();

        $this->get(route('finance.teams.transactions.index', [
            'team' => $team->getRouteKey(),
            'filter' => [
                'from' => '2024-06-30',
                'to' => '2024-06-01',
            ],
        ]))
            ->assertRedirect()
            ->assertInvalid(['filter.to']);
    });

    it('rejects an account filter from another team', function (): void {
        [$user, $team] = actingMember();
        $foreign = Account::factory()->for(Team::factory())->create();

        $this->get(route('finance.teams.transactions.index', [
            'team' => $team->getRouteKey(),
            'filter' => ['account' => $foreign->getKey()],
        ]))
            ->assertRedirect()
            ->assertInvalid(['filter.account']);
    });

    it('filters transactions that have any of the given tags', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create();
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);
        $comida = Tag::factory()->active()->for($team)->create(['name' => 'Comida']);
        $salidas = Tag::factory()->active()->for($team)->create(['name' => 'Salidas']);
        $matching = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);
        $matching->tags()->attach($comida->getKey());
        $other = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);
        $other->tags()->attach($salidas->getKey());

        $this->get(route('finance.teams.transactions.index', [
            'team' => $team->getRouteKey(),
            'filter' => [
                'tags' => $comida->getKey(),
            ],
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('filters.tags', $comida->getKey())
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('transactions.data', 1)
                    ->where('transactions.data.0.id', $matching->getKey())));
    });

    it('filters both transfer legs with the transfer type', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create();
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);
        $transfer = Transfer::factory()->for($team)->create();
        $legs = collect([
            Transaction::factory()->transferOut(),
            Transaction::factory()->transferIn(),
        ])->map(fn (\Database\Factories\Modules\Core\Finance\Models\Team\TransactionFactory $factory): Transaction => $factory->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'transfer_id' => $transfer->getKey(),
            'user_id' => $user->getKey(),
        ]));
        Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $this->get(route('finance.teams.transactions.index', [
            'team' => $team->getRouteKey(),
            'filter' => ['type' => Transaction::TRANSFER_TYPE],
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('filters.type', Transaction::TRANSFER_TYPE)
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('transactions.data', 2)
                    ->where('transactions.data.0.transfer_id', $transfer->getKey())
                    ->where('transactions.data.1.transfer_id', $transfer->getKey())));

        expect($legs)->toHaveCount(2);
    });

    it('rejects a tag filter from another team', function (): void {
        [$user, $team] = actingMember();
        $foreign = Tag::factory()->for(Team::factory())->create();

        $this->get(route('finance.teams.transactions.index', [
            'team' => $team->getRouteKey(),
            'filter' => ['tags' => $foreign->getKey()],
        ]))
            ->assertRedirect()
            ->assertInvalid(['filter.tags.0']);
    });

    it('hides transactions older than the window the free plan keeps', function (): void {
        [$user, $team] = actingMember();
        $account = Account::factory()->bank()->active()->for($team)->create();
        $start = CarbonImmutable::now()->startOfMonth()->subMonths(5);

        $older = Transaction::factory()->expense()->for($account)->for($team)->create([
            'occurred_at' => $start->subDay(),
        ]);
        $onTheEdge = Transaction::factory()->expense()->for($account)->for($team)->create([
            'occurred_at' => $start,
        ]);

        $this->get(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('history.limited', true)
                ->where('history.months', 6)
                ->where('history.since', $start->toDateString())
                ->where('history.has_older', true)
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('transactions.data', 1)
                    ->where('transactions.data.0.id', $onTheEdge->getKey())));

        expect($older->fresh())->not->toBeNull();
    });

    it('reports no older transactions when the free team has none', function (): void {
        [$user, $team] = actingMember();
        $account = Account::factory()->bank()->active()->for($team)->create();

        Transaction::factory()->expense()->for($account)->for($team)->create([
            'occurred_at' => CarbonImmutable::now()->subMonth(),
        ]);

        $this->get(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('history.limited', true)
                ->where('history.has_older', false));
    });

    it('raises a from filter the free plan no longer reaches', function (): void {
        [$user, $team] = actingMember();
        $account = Account::factory()->bank()->active()->for($team)->create();
        $start = CarbonImmutable::now()->startOfMonth()->subMonths(5);

        Transaction::factory()->expense()->for($account)->for($team)->create([
            'occurred_at' => $start->subYear(),
        ]);
        $visible = Transaction::factory()->expense()->for($account)->for($team)->create([
            'occurred_at' => $start->addDays(3),
        ]);

        $this->get(route('finance.teams.transactions.index', [
            'team' => $team->getRouteKey(),
            'filter' => ['from' => $start->subYear()->toDateString()],
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('filters.from', $start->toDateString())
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('transactions.data', 1)
                    ->where('transactions.data.0.id', $visible->getKey())));
    });

    it('leaves a from filter inside the window untouched', function (): void {
        [$user, $team] = actingMember();
        $account = Account::factory()->bank()->active()->for($team)->create();
        $from = CarbonImmutable::now()->startOfMonth()->subMonths(2);

        Transaction::factory()->expense()->for($account)->for($team)->create([
            'occurred_at' => $from->addDay(),
        ]);

        $this->get(route('finance.teams.transactions.index', [
            'team' => $team->getRouteKey(),
            'filter' => ['from' => $from->toDateString()],
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('filters.from', $from->toDateString()));
    });

    it('does not invent a from filter the request never carried', function (): void {
        [$user, $team] = actingMember();

        $this->get(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->missing('filters.from'));
    });

    it('finds nothing when searching the id of a hidden transaction', function (): void {
        [$user, $team] = actingMember();
        $account = Account::factory()->bank()->active()->for($team)->create();

        $older = Transaction::factory()->expense()->for($account)->for($team)->create([
            'occurred_at' => CarbonImmutable::now()->startOfMonth()->subMonths(8),
        ]);

        $this->get(route('finance.teams.transactions.index', [
            'team' => $team->getRouteKey(),
            'filter' => ['search' => $older->getKey()],
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('transactions.data', 0)));
    });

    it('lists every transaction for a pro team', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        $account = Account::factory()->bank()->active()->for($team)->create();

        $older = Transaction::factory()->expense()->for($account)->for($team)->create([
            'occurred_at' => CarbonImmutable::now()->subYears(3),
        ]);
        Transaction::factory()->expense()->for($account)->for($team)->create([
            'occurred_at' => CarbonImmutable::now(),
        ]);

        $this->get(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('history.limited', false)
                ->where('history.since', null)
                ->where('history.months', null)
                ->where('history.has_older', false)
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('transactions.data', 2)
                    ->where('transactions.data.1.id', $older->getKey())));
    });

    it('never lists another team within the window', function (): void {
        [$user, $team] = actingMember();
        $account = Account::factory()->bank()->active()->for($team)->create();
        $foreign = Account::factory()->bank()->active()->for(Team::factory())->create();

        $mine = Transaction::factory()->expense()->for($account)->for($team)->create([
            'occurred_at' => CarbonImmutable::now(),
        ]);
        Transaction::factory()->expense()->for($foreign)->create([
            'occurred_at' => CarbonImmutable::now(),
        ]);

        $this->get(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('transactions.data', 1)
                    ->where('transactions.data.0.id', $mine->getKey())));
    });
});

describe('export', function (): void {
    it('downloads a CSV attachment named after the team and the export date', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create();
        $account = Account::factory()->bank()->active()->for($team)->create();
        Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $response = $this->get(route('finance.teams.transactions.export', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $response->assertDownload("{$team->getRouteKey()}-transactions-".CarbonImmutable::now()->toDateString().'.csv');
    });

    it('starts the body with a byte-order mark and the English header row', function (): void {
        [, $team] = actingMember();

        $body = $this->get(route('finance.teams.transactions.export', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->streamedContent();

        expect($body)->toStartWith("\u{FEFF}");

        expect(csvRows($body)[0])->toBe([
            'Date',
            'Time',
            'Type',
            'Amount',
            'Currency',
            'Description',
            'Account',
            'Tags',
            'Recorded by',
            'FX rate',
            'Id',
        ]);
    });

    it('translates the header row for the requested locale', function (): void {
        [, $team] = actingMember();

        $body = $this->withHeaders(['Accept-Language' => 'es'])
            ->get(route('finance.teams.transactions.export', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->streamedContent();

        expect(csvRows($body)[0])->toBe([
            'Fecha',
            'Hora',
            'Tipo',
            'Monto',
            'Moneda',
            'Descripción',
            'Cuenta',
            'Etiquetas',
            'Registró',
            'Tipo de cambio',
            'Id',
        ]);
    });

    it('writes one row per transaction with its columns in order', function (): void {
        [$user, $team] = actingMember();
        $user->update(['name' => 'Ada Lovelace']);
        $currency = Currency::factory()->active()->fiat()->create(['code' => 'UYU']);
        $account = Account::factory()->bank()->active()->for($team)->create(['name' => 'Santander']);
        $transaction = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'amount' => '1234.5',
            'currency_id' => $currency->getKey(),
            'decimal_places' => 2,
            'description' => 'Almuerzo compartido',
            'occurred_at' => CarbonImmutable::now()->setTime(15, 45),
            'quote' => '38.5',
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);
        $transaction->tags()->attach([
            Tag::factory()->active()->for($team)->create(['name' => 'Salidas'])->getKey(),
            Tag::factory()->active()->for($team)->create(['name' => 'Comida'])->getKey(),
        ]);

        $body = $this->get(route('finance.teams.transactions.export', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->streamedContent();

        $rows = csvRows($body);

        expect($rows)->toHaveCount(2);
        expect($rows[1])->toBe([
            $transaction->occurred_at->format('Y-m-d'),
            '15:45',
            'Expense',
            '-1234.50',
            'UYU',
            'Almuerzo compartido',
            'Santander',
            'Comida; Salidas',
            'Ada Lovelace',
            '38.5',
            $transaction->getKey(),
        ]);
    });

    it('writes an income amount without a sign', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create();
        $account = Account::factory()->bank()->active()->for($team)->create();
        Transaction::factory()->income()->create([
            'account_id' => $account->getKey(),
            'amount' => '90',
            'currency_id' => $currency->getKey(),
            'decimal_places' => 0,
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $body = $this->get(route('finance.teams.transactions.export', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->streamedContent();

        expect(csvRows($body)[1][3])->toBe('90');
    });

    it('writes the date and time in the requested timezone', function (): void {
        [$user, $team] = actingMember();
        $account = Account::factory()->bank()->active()->for($team)->create();
        Transaction::factory()->expense()->for($account)->for($team)->create([
            'occurred_at' => CarbonImmutable::now()->startOfMonth()->addDays(9)->setTime(2, 30),
        ]);

        $body = $this->get(route('finance.teams.transactions.export', [
            'team' => $team->getRouteKey(),
            'timezone' => 'America/Montevideo',
        ]))
            ->assertOk()
            ->streamedContent();

        $expected = CarbonImmutable::now()->startOfMonth()->addDays(9)->setTime(2, 30)
            ->setTimezone('America/Montevideo');

        expect(csvRows($body)[1][0])->toBe($expected->format('Y-m-d'));
        expect(csvRows($body)[1][1])->toBe($expected->format('H:i'));
    });

    it('falls back to UTC when no timezone is given', function (): void {
        [$user, $team] = actingMember();
        $account = Account::factory()->bank()->active()->for($team)->create();
        $occurredAt = CarbonImmutable::now()->startOfMonth()->addDays(9)->setTime(2, 30);
        Transaction::factory()->expense()->for($account)->for($team)->create([
            'occurred_at' => $occurredAt,
        ]);

        $body = $this->get(route('finance.teams.transactions.export', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->streamedContent();

        expect(csvRows($body)[1][0])->toBe($occurredAt->format('Y-m-d'));
        expect(csvRows($body)[1][1])->toBe('02:30');
    });

    it('rejects a timezone that is not an identifier', function (): void {
        [, $team] = actingMember();

        $this->get(route('finance.teams.transactions.export', [
            'team' => $team->getRouteKey(),
            'timezone' => 'Mars/Olympus',
        ]))
            ->assertRedirect()
            ->assertInvalid(['timezone']);
    });

    it('exports only the transactions the filters match', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create();
        $otherCurrency = Currency::factory()->active()->fiat()->create();
        $account = Account::factory()->bank()->active()->for($team)->create();
        $otherAccount = Account::factory()->bank()->active()->for($team)->create();
        $tag = Tag::factory()->active()->for($team)->create();

        $month = CarbonImmutable::now()->startOfMonth()->subMonth();

        $matching = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'description' => 'Office lunch',
            'occurred_at' => $month->addDays(14),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);
        $matching->tags()->attach($tag->getKey());

        Transaction::factory()->income()->create([
            'account_id' => $otherAccount->getKey(),
            'currency_id' => $otherCurrency->getKey(),
            'description' => 'Salary',
            'occurred_at' => $month->subMonths(2),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $body = $this->get(route('finance.teams.transactions.export', [
            'team' => $team->getRouteKey(),
            'filter' => [
                'type' => TransactionType::Expense->value,
                'account' => $account->getKey(),
                'currency' => $currency->getKey(),
                'search' => 'lunch',
                'tags' => $tag->getKey(),
                'from' => $month->toDateString(),
                'to' => $month->endOfMonth()->toDateString(),
            ],
        ]))
            ->assertOk()
            ->streamedContent();

        $rows = csvRows($body);

        expect($rows)->toHaveCount(2);
        expect($rows[1][10])->toBe($matching->getKey());
    });

    it('exports every visible transaction of the team, newest first, and nothing from another team', function (): void {
        [$user, $team] = actingMember();
        $account = Account::factory()->bank()->active()->for($team)->create();

        $older = Transaction::factory()->expense()->for($account)->for($team)->create([
            'occurred_at' => CarbonImmutable::now()->subDays(2),
        ]);
        $newer = Transaction::factory()->expense()->for($account)->for($team)->create([
            'occurred_at' => CarbonImmutable::now(),
        ]);
        Transaction::factory()->expense()->create([
            'occurred_at' => CarbonImmutable::now(),
        ]);

        $body = $this->get(route('finance.teams.transactions.export', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->streamedContent();

        $rows = csvRows($body);

        expect($rows)->toHaveCount(3);
        expect($rows[1][10])->toBe($newer->getKey());
        expect($rows[2][10])->toBe($older->getKey());
    });

    it('leaves out a transaction the free plan no longer shows', function (): void {
        [, $team] = actingMember();
        $account = Account::factory()->bank()->active()->for($team)->create();

        Transaction::factory()->expense()->for($account)->for($team)->create([
            'occurred_at' => CarbonImmutable::now()->startOfMonth()->subMonths(5)->subDay(),
        ]);
        $visible = Transaction::factory()->expense()->for($account)->for($team)->create([
            'occurred_at' => CarbonImmutable::now(),
        ]);

        $body = $this->get(route('finance.teams.transactions.export', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->streamedContent();

        $rows = csvRows($body);

        expect($rows)->toHaveCount(2);
        expect($rows[1][10])->toBe($visible->getKey());
    });

    it('writes only the header row when nothing matches', function (): void {
        [, $team] = actingMember();

        $body = $this->get(route('finance.teams.transactions.export', [
            'team' => $team->getRouteKey(),
            'filter' => ['search' => 'nothing here'],
        ]))
            ->assertOk()
            ->streamedContent();

        expect(csvRows($body))->toHaveCount(1);
    });

    it('leaves the recorded by cell empty when the member is gone', function (): void {
        [, $team] = actingMember();
        $account = Account::factory()->bank()->active()->for($team)->create();
        Transaction::factory()->expense()->for($account)->for($team)->create([
            'description' => null,
            'user_id' => null,
        ]);

        $body = $this->get(route('finance.teams.transactions.export', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->streamedContent();

        $row = csvRows($body)[1];

        expect($row[8])->toBe('');
        expect($row[5])->toBe('');
    });

    it('rejects an account filter from another team', function (): void {
        [, $team] = actingMember();
        $foreign = Account::factory()->for(Team::factory())->create();

        $this->get(route('finance.teams.transactions.export', [
            'team' => $team->getRouteKey(),
            'filter' => ['account' => $foreign->getKey()],
        ]))
            ->assertRedirect()
            ->assertInvalid(['filter.account']);
    });

    it('forbids a user who is not a member of the team', function (): void {
        actingMember();
        $other = Team::factory()->create();

        $this->get(route('finance.teams.transactions.export', ['team' => $other->getRouteKey()]))
            ->assertForbidden();
    });

    it('throttles the eleventh export within a minute', function (): void {
        [, $team] = actingMember();

        $url = route('finance.teams.transactions.export', ['team' => $team->getRouteKey()]);

        foreach (range(1, 10) as $ignored) {
            $this->get($url)->assertOk();
        }

        $this->get($url)->assertTooManyRequests();
    });
});

describe('create', function (): void {
    it('renders the create transaction modal for each type', function (TransactionType $type): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create();
        $quote = Quote::factory()->for($currency)->create(['observed_at' => '2024-06-01 00:00:00', 'rate' => '1.5']);
        $inactiveCurrency = Currency::factory()->inactive()->fiat()->create();
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach([$currency->getKey(), $inactiveCurrency->getKey()]);
        Account::factory()->bank()->inactive()->for($team)->create();
        $tag = Tag::factory()->active()->for($team)->create();
        Tag::factory()->inactive()->for($team)->create();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.transactions.create', [
                'team' => $team->getRouteKey(),
                'type' => $type->value,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('finance/teams/transactions/create')
                ->has('accounts', 1)
                ->where('accounts.0.id', $account->getKey())
                ->has('accounts.0.currencies', 1)
                ->where('accounts.0.currencies.0.id', $currency->getKey())
                ->has('accounts.0.currencies.0.quotes', 1)
                ->where('accounts.0.currencies.0.quotes.0.id', $quote->getKey())
                ->has('tags', 1)
                ->where('tags.0.id', $tag->getKey())
                ->where('type', $type->value));
    })->with([
        'expense' => TransactionType::Expense,
        'income' => TransactionType::Income,
    ]);

    it('returns 404 for a transaction type that cannot be created here', function (string $type): void {
        [$user, $team] = actingMember();

        $this->get('/teams/'.$team->getRouteKey().'/transactions/create/'.$type)
            ->assertNotFound();
    })->with([
        'opening balance' => TransactionType::OpeningBalance->value,
        'transfer in' => TransactionType::TransferIn->value,
        'transfer out' => TransactionType::TransferOut->value,
        'unknown' => 'transfer',
    ]);
});

describe('store', function (): void {
    it('creates a transaction copying decimal places from the currency', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transactions.store', ['team' => $team->getRouteKey()]), transactionPayload([
                $account->getForeignKey() => $account->getKey(),
                $currency->getForeignKey() => $currency->getKey(),
            ]))
            ->assertRedirect();

        assertFlashedToast(trans('Saved'));

        $transaction = Transaction::query()->sole();
        expect($transaction->decimal_places)->toBe(2)
            ->and($transaction->user?->is($user))->toBeTrue()
            ->and($transaction->type)->toBe(TransactionType::Expense)
            ->and($transaction->tags)->toHaveCount(0);
    });

    it('stores a datetime-local occurred_at in UTC using the submitted timezone', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transactions.store', ['team' => $team->getRouteKey()]), transactionPayload([
                $account->getForeignKey() => $account->getKey(),
                $currency->getForeignKey() => $currency->getKey(),
                'occurred_at' => '2026-09-06T12:21',
                'timezone' => 'America/Argentina/Buenos_Aires',
            ]))
            ->assertRedirect();

        expect(Transaction::query()->sole()->occurred_at->toDateTimeString())->toBe('2026-09-06 15:21:00');
    });

    it('keeps a datetime-local occurred_at when the timezone is invalid', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transactions.store', ['team' => $team->getRouteKey()]), transactionPayload([
                $account->getForeignKey() => $account->getKey(),
                $currency->getForeignKey() => $currency->getKey(),
                'occurred_at' => '2026-09-06T12:21',
                'timezone' => 'Not/AZone',
            ]))
            ->assertRedirect();

        expect(Transaction::query()->sole()->occurred_at->toDateTimeString())->toBe('2026-09-06 12:21:00');
    });

    it('keeps a datetime-local occurred_at when the format is invalid', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transactions.store', ['team' => $team->getRouteKey()]), transactionPayload([
                $account->getForeignKey() => $account->getKey(),
                $currency->getForeignKey() => $currency->getKey(),
                'occurred_at' => '2026-09-06 12:21:00',
                'timezone' => 'UTC',
            ]))
            ->assertRedirect();

        expect(Transaction::query()->sole()->occurred_at->toDateTimeString())->toBe('2026-09-06 12:21:00');
    });

    it('rejects a datetime-local occurred_at that cannot be parsed', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transactions.store', ['team' => $team->getRouteKey()]), transactionPayload([
                $account->getForeignKey() => $account->getKey(),
                $currency->getForeignKey() => $currency->getKey(),
                'occurred_at' => '2026-99-99T12:00',
                'timezone' => 'UTC',
                'quote' => '',
            ]))
            ->assertRedirect()
            ->assertInvalid(['occurred_at']);
    });

    it('attaches tags when creating a transaction', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);
        $comida = Tag::factory()->active()->for($team)->create();
        $salidas = Tag::factory()->active()->for($team)->create();

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transactions.store', ['team' => $team->getRouteKey()]), transactionPayload([
                $account->getForeignKey() => $account->getKey(),
                $currency->getForeignKey() => $currency->getKey(),
                'tag_ids' => [$comida->getKey(), $salidas->getKey()],
            ]))
            ->assertRedirect();

        $transaction = Transaction::query()->sole();
        expect($transaction->tags()->pluck($comida->qualifyColumn($comida->getKeyName()))->all())
            ->toEqualCanonicalizing([$comida->getKey(), $salidas->getKey()]);
    });

    it('rejects duplicate tag ids', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);
        $tag = Tag::factory()->active()->for($team)->create();

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transactions.store', ['team' => $team->getRouteKey()]), transactionPayload([
                $account->getForeignKey() => $account->getKey(),
                $currency->getForeignKey() => $currency->getKey(),
                'tag_ids' => [$tag->getKey(), $tag->getKey()],
            ]))
            ->assertRedirect()
            ->assertInvalid(['tag_ids.0']);
    });

    it('rejects an inactive tag', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);
        $tag = Tag::factory()->inactive()->for($team)->create();

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transactions.store', ['team' => $team->getRouteKey()]), transactionPayload([
                $account->getForeignKey() => $account->getKey(),
                $currency->getForeignKey() => $currency->getKey(),
                'tag_ids' => [$tag->getKey()],
            ]))
            ->assertRedirect()
            ->assertInvalid(['tag_ids.0']);
    });

    it('rejects a tag from another team', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);
        $tag = Tag::factory()->active()->for(Team::factory())->create();

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transactions.store', ['team' => $team->getRouteKey()]), transactionPayload([
                $account->getForeignKey() => $account->getKey(),
                $currency->getForeignKey() => $currency->getKey(),
                'tag_ids' => [$tag->getKey()],
            ]))
            ->assertRedirect()
            ->assertInvalid(['tag_ids.0']);
    });

    it('defaults the quote from the rate as of occurred_at when omitted', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        Quote::factory()->for($currency)->create([
            'observed_at' => '2024-06-01 00:00:00',
            'rate' => '1.5',
        ]);
        Quote::factory()->for($currency)->create([
            'observed_at' => '2024-06-15 00:00:00',
            'rate' => '2',
        ]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);

        $payload = transactionPayload([
            $account->getForeignKey() => $account->getKey(),
            $currency->getForeignKey() => $currency->getKey(),
            'occurred_at' => '2024-06-01 12:00:00',
        ]);
        unset($payload['quote']);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transactions.store', ['team' => $team->getRouteKey()]), $payload)
            ->assertRedirect();

        expect(Transaction::query()->sole()->quote)->toBe('1.500000000000000000');
    });

    it('rejects an omitted quote when no rate exists as of occurred_at', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        Quote::factory()->for($currency)->create([
            'observed_at' => '2024-06-15 00:00:00',
            'rate' => '2',
        ]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);

        $payload = transactionPayload([
            $account->getForeignKey() => $account->getKey(),
            $currency->getForeignKey() => $currency->getKey(),
            'occurred_at' => '2024-01-01 12:00:00',
        ]);
        unset($payload['quote']);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transactions.store', ['team' => $team->getRouteKey()]), $payload)
            ->assertRedirect()
            ->assertInvalid(['quote']);
    });

    it('ignores quotes observed after occurred_at when defaulting the rate', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        Quote::factory()->for($currency)->create([
            'observed_at' => '2024-06-01 00:00:00',
            'rate' => '1.5',
        ]);
        Quote::factory()->for($currency)->create([
            'observed_at' => '2024-07-01 00:00:00',
            'rate' => '3',
        ]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);

        $payload = transactionPayload([
            $account->getForeignKey() => $account->getKey(),
            $currency->getForeignKey() => $currency->getKey(),
            'occurred_at' => '2024-06-15 12:00:00',
        ]);
        unset($payload['quote']);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transactions.store', ['team' => $team->getRouteKey()]), $payload)
            ->assertRedirect();

        expect(Transaction::query()->sole()->quote)->toBe('1.500000000000000000');
    });

    it('defaults the quote as of a datetime-local occurred_at', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        Quote::factory()->for($currency)->create([
            'observed_at' => '2026-09-06 00:00:00',
            'rate' => '40',
        ]);
        Quote::factory()->for($currency)->create([
            'observed_at' => '2026-09-07 00:00:00',
            'rate' => '41',
        ]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);

        $payload = transactionPayload([
            $account->getForeignKey() => $account->getKey(),
            $currency->getForeignKey() => $currency->getKey(),
            'occurred_at' => '2026-09-06T12:21',
            'timezone' => 'America/Argentina/Buenos_Aires',
        ]);
        unset($payload['quote']);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transactions.store', ['team' => $team->getRouteKey()]), $payload)
            ->assertRedirect();

        expect(Transaction::query()->sole()->quote)->toBe('40.000000000000000000');
    });

    it('keeps a submitted quote instead of the as-of rate', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        Quote::factory()->for($currency)->create([
            'observed_at' => '2024-06-01 00:00:00',
            'rate' => '1.5',
        ]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transactions.store', ['team' => $team->getRouteKey()]), transactionPayload([
                $account->getForeignKey() => $account->getKey(),
                $currency->getForeignKey() => $currency->getKey(),
                'quote' => '41.25',
            ]))
            ->assertRedirect();

        expect(Transaction::query()->sole()->quote)->toBe('41.250000000000000000');
    });

    it('rejects a currency that is not attached to the account', function (): void {
        [$user, $team] = actingMember();
        $attached = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $other = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($attached);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transactions.store', ['team' => $team->getRouteKey()]), transactionPayload([
                $account->getForeignKey() => $account->getKey(),
                $other->getForeignKey() => $other->getKey(),
            ]))
            ->assertRedirect()
            ->assertInvalid([$other->getForeignKey()]);
    });

    it('rejects an account from another team', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $foreign = Account::factory()->bank()->active()->for(Team::factory())->create();
        $foreign->currencies()->attach($currency);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transactions.store', ['team' => $team->getRouteKey()]), transactionPayload([
                $foreign->getForeignKey() => $foreign->getKey(),
                $currency->getForeignKey() => $currency->getKey(),
            ]))
            ->assertRedirect()
            ->assertInvalid([$foreign->getForeignKey()]);
    });

    it('rejects an empty payload', function (): void {
        [$user, $team] = actingMember();

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transactions.store', ['team' => $team->getRouteKey()]), [])
            ->assertRedirect()
            ->assertInvalid(['amount', 'occurred_at', 'quote', 'type']);
    });

    it('rejects the opening balance type', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.transactions.store', ['team' => $team->getRouteKey()]), transactionPayload([
                $account->getForeignKey() => $account->getKey(),
                $currency->getForeignKey() => $currency->getKey(),
                'type' => TransactionType::OpeningBalance->value,
            ]))
            ->assertRedirect()
            ->assertInvalid(['type']);

        expect(Transaction::query()->count())->toBe(0);
    });

    it('records the transaction and its tags as one entry in the activity log', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);
        $tag = Tag::factory()->for($team)->create();
        clearActivities();

        $this->post(route('finance.teams.transactions.store', ['team' => $team->getRouteKey()]), transactionPayload([
            $account->getForeignKey() => $account->getKey(),
            $currency->getForeignKey() => $currency->getKey(),
            'tag_ids' => [$tag->getKey()],
        ]))->assertRedirect();

        $transaction = Transaction::query()->sole();
        $activities = activitiesOf($team);

        expect($activities->pluck('event')->all())->toBe(['created', 'tags_synced'])
            ->and($activities->pluck('batch_uuid')->unique()->filter()->all())->toHaveCount(1)
            ->and($activities->first()?->causer?->is($user))->toBeTrue()
            ->and($activities->first()?->subject?->is($transaction))->toBeTrue()
            ->and($activities->first()?->attribute_changes['attributes']['amount'])->toBe($transaction->amount)
            ->and($activities->first()?->attribute_changes['attributes']['description'])->toBe('Lunch')
            ->and($activities->last()?->attribute_changes['old']['tags'])->toBe([])
            ->and($activities->last()?->attribute_changes['attributes']['tags'])->toBe([$tag->getKey()]);
    });
});

describe('show', function (): void {
    it('renders the transaction modal', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create();
        $account = Account::factory()->bank()->active()->for($team)->create();
        $transaction = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);
        $tag = Tag::factory()->active()->for($team)->create();
        $transaction->tags()->attach($tag->getKey());

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.transactions.show', [
                'team' => $team->getRouteKey(),
                'transaction' => $transaction->getKey(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('finance/teams/transactions/show')
                ->where('transaction.id', $transaction->getKey())
                ->where('transaction.user.id', $user->getKey())
                ->where('transaction.user.name', $user->name)
                ->where('transaction.account.name', $account->name)
                ->has('transaction.currency')
                ->has('transaction.tags', 1)
                ->where('transaction.tags.0.id', $tag->getKey()));
    });

    it('renders the transaction modal when nobody is recorded', function (): void {
        [, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create();
        $account = Account::factory()->bank()->active()->for($team)->create();
        $transaction = Transaction::factory()->income()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'description' => null,
            'team_id' => $team->getKey(),
            'user_id' => null,
        ]);

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.transactions.show', [
                'team' => $team->getRouteKey(),
                'transaction' => $transaction->getKey(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('finance/teams/transactions/show')
                ->where('transaction.id', $transaction->getKey())
                ->where('transaction.user', null)
                ->where('transaction.description', null)
                ->has('transaction.tags', 0));
    });

    it('returns 404 when the transaction belongs to another team', function (): void {
        [$user, $team] = actingMember();
        $foreign = Transaction::factory()->create();

        $this->get(route('finance.teams.transactions.show', [
            'team' => $team->getRouteKey(),
            'transaction' => $foreign->getKey(),
        ]))->assertNotFound();
    });

    it('reports a transaction older than the free window as not found', function (): void {
        [$user, $team] = actingMember();
        $account = Account::factory()->bank()->active()->for($team)->create();
        $older = Transaction::factory()->expense()->for($account)->for($team)->create([
            'occurred_at' => CarbonImmutable::now()->startOfMonth()->subMonths(5)->subDay(),
        ]);

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.transactions.show', [
                'team' => $team->getRouteKey(),
                'transaction' => $older->getKey(),
            ]))
            ->assertNotFound();
    });

    it('renders a transaction older than the free window for a pro team', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $older = Transaction::factory()->expense()->for($account)->for($team)->create([
            'occurred_at' => CarbonImmutable::now()->subYears(2),
        ]);

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.transactions.show', [
                'team' => $team->getRouteKey(),
                'transaction' => $older->getKey(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('transaction.id', $older->getKey()));
    });
});

describe('edit', function (): void {
    it('renders the edit transaction modal with its accounts and tags', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create();
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);
        $tag = Tag::factory()->active()->for($team)->create();
        $transaction = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);
        $transaction->attachTags([$tag->getKey()]);

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.transactions.edit', [
                'team' => $team->getRouteKey(),
                'transaction' => $transaction->getKey(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('finance/teams/transactions/edit')
                ->where('transaction.id', $transaction->getKey())
                ->has('accounts', 1)
                ->where('accounts.0.id', $account->getKey())
                ->has('tags', 1)
                ->where('tags.0.id', $tag->getKey()));
    });

    it('includes the inactive current account and its inactive currency', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create();
        $account = Account::factory()->bank()->inactive()->for($team)->create();
        $account->currencies()->attach($currency);
        $transaction = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);
        $currency->deactivate();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.transactions.edit', [
                'team' => $team->getRouteKey(),
                'transaction' => $transaction->getKey(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->has('accounts', 1)
                ->where('accounts.0.id', $account->getKey())
                ->has('accounts.0.currencies', 1)
                ->where('accounts.0.currencies.0.id', $currency->getKey()));
    });

    it('includes an inactive tag currently attached to the transaction', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create();
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);
        $tag = Tag::factory()->active()->for($team)->create();
        $transaction = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);
        $transaction->attachTags([$tag->getKey()]);
        $tag->deactivate();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.transactions.edit', [
                'team' => $team->getRouteKey(),
                'transaction' => $transaction->getKey(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->has('tags', 1)
                ->where('tags.0.id', $tag->getKey()));
    });

    it('returns 404 when the transaction belongs to another team', function (): void {
        [$user, $team] = actingMember();
        $foreign = Transaction::factory()->create();

        $this->get(route('finance.teams.transactions.edit', [
            'team' => $team->getRouteKey(),
            'transaction' => $foreign->getKey(),
        ]))->assertNotFound();
    });
});

describe('update', function (): void {
    it('updates each editable field and flashes Saved', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency, ['balance' => '89.50']);
        $transaction = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'amount' => '10.50',
            'currency_id' => $currency->getKey(),
            'description' => 'Lunch',
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.transactions.update', [
                'team' => $team->getRouteKey(),
                'transaction' => $transaction->getKey(),
            ]), transactionPayload([
                $account->getForeignKey() => $account->getKey(),
                $currency->getForeignKey() => $currency->getKey(),
                'amount' => '20.50',
                'description' => 'Dinner',
            ]))
            ->assertRedirect();

        assertFlashedToast(trans('Saved'));

        $updated = $transaction->fresh();
        expect($updated?->getKey())->toBe($transaction->getKey())
            ->and($updated?->amount)->toBe('20.500000000000000000')
            ->and($updated?->description)->toBe('Dinner')
            ->and($updated?->user?->is($user))->toBeTrue();
    });

    it('moves the balance when the amount changes', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency, ['balance' => '89.50']);
        $transaction = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'amount' => '10.50',
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.transactions.update', [
                'team' => $team->getRouteKey(),
                'transaction' => $transaction->getKey(),
            ]), transactionPayload([
                $account->getForeignKey() => $account->getKey(),
                $currency->getForeignKey() => $currency->getKey(),
                'amount' => '20.50',
            ]))
            ->assertRedirect();

        expect((float) $account->currencies()->firstOrFail()->pivot->balance)->toBe(79.5);
    });

    it('moves the balance when the type flips', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency, ['balance' => '89.50']);
        $transaction = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'amount' => '10.50',
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.transactions.update', [
                'team' => $team->getRouteKey(),
                'transaction' => $transaction->getKey(),
            ]), transactionPayload([
                $account->getForeignKey() => $account->getKey(),
                $currency->getForeignKey() => $currency->getKey(),
                'amount' => '10.50',
                'type' => TransactionType::Income->value,
            ]))
            ->assertRedirect();

        expect((float) $account->currencies()->firstOrFail()->pivot->balance)->toBe(110.5);
    });

    it('moves the balance when the account changes', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $originAccount = Account::factory()->bank()->active()->for($team)->create();
        $destinationAccount = Account::factory()->bank()->active()->for($team)->create();
        $originAccount->currencies()->attach($currency, ['balance' => '89.50']);
        $destinationAccount->currencies()->attach($currency, ['balance' => '50.00']);
        $transaction = Transaction::factory()->expense()->create([
            'account_id' => $originAccount->getKey(),
            'amount' => '10.50',
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.transactions.update', [
                'team' => $team->getRouteKey(),
                'transaction' => $transaction->getKey(),
            ]), transactionPayload([
                $destinationAccount->getForeignKey() => $destinationAccount->getKey(),
                $currency->getForeignKey() => $currency->getKey(),
                'amount' => '10.50',
            ]))
            ->assertRedirect();

        expect((float) $originAccount->currencies()->firstOrFail()->pivot->balance)->toBe(100.0)
            ->and((float) $destinationAccount->currencies()->firstOrFail()->pivot->balance)->toBe(39.5)
            ->and($transaction->fresh()?->account_id)->toBe($destinationAccount->getKey());
    });

    it('moves the balance when the currency changes', function (): void {
        [$user, $team] = actingMember();
        $originCurrency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $destinationCurrency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($originCurrency, ['balance' => '89.50']);
        $account->currencies()->attach($destinationCurrency, ['balance' => '20.00']);
        $transaction = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'amount' => '10.50',
            'currency_id' => $originCurrency->getKey(),
            'decimal_places' => 2,
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.transactions.update', [
                'team' => $team->getRouteKey(),
                'transaction' => $transaction->getKey(),
            ]), transactionPayload([
                $account->getForeignKey() => $account->getKey(),
                $destinationCurrency->getForeignKey() => $destinationCurrency->getKey(),
                'amount' => '10.50',
            ]))
            ->assertRedirect();

        expect((float) $account->currencies()->whereKey($originCurrency->getKey())->firstOrFail()->pivot->balance)->toBe(100.0)
            ->and((float) $account->currencies()->whereKey($destinationCurrency->getKey())->firstOrFail()->pivot->balance)->toBe(9.5)
            ->and($transaction->fresh()?->currency_id)->toBe($destinationCurrency->getKey())
            ->and($transaction->fresh()?->decimal_places)->toBe($destinationCurrency->decimal_places);
    });

    it('moves the balance correctly when several fields change at once', function (): void {
        [$user, $team] = actingMember();
        $originCurrency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $destinationCurrency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $originAccount = Account::factory()->bank()->active()->for($team)->create();
        $destinationAccount = Account::factory()->bank()->active()->for($team)->create();
        $originAccount->currencies()->attach($originCurrency, ['balance' => '89.50']);
        $destinationAccount->currencies()->attach($destinationCurrency, ['balance' => '20.00']);
        $transaction = Transaction::factory()->expense()->create([
            'account_id' => $originAccount->getKey(),
            'amount' => '10.50',
            'currency_id' => $originCurrency->getKey(),
            'decimal_places' => 2,
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.transactions.update', [
                'team' => $team->getRouteKey(),
                'transaction' => $transaction->getKey(),
            ]), transactionPayload([
                $destinationAccount->getForeignKey() => $destinationAccount->getKey(),
                $destinationCurrency->getForeignKey() => $destinationCurrency->getKey(),
                'amount' => '15.00',
                'type' => TransactionType::Income->value,
            ]))
            ->assertRedirect();

        expect((float) $originAccount->currencies()->firstOrFail()->pivot->balance)->toBe(100.0)
            ->and((float) $destinationAccount->currencies()->firstOrFail()->pivot->balance)->toBe(35.0);
    });

    it('replaces the tag set', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);
        $old = Tag::factory()->active()->for($team)->create();
        $new = Tag::factory()->active()->for($team)->create();
        $transaction = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);
        $transaction->attachTags([$old->getKey()]);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.transactions.update', [
                'team' => $team->getRouteKey(),
                'transaction' => $transaction->getKey(),
            ]), transactionPayload([
                $account->getForeignKey() => $account->getKey(),
                $currency->getForeignKey() => $currency->getKey(),
                'tag_ids' => [$new->getKey()],
            ]))
            ->assertRedirect();

        expect($transaction->tags()->pluck($new->qualifyColumn($new->getKeyName()))->all())->toBe([$new->getKey()]);
    });

    it('keeps a resubmitted already-attached inactive tag', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);
        $tag = Tag::factory()->active()->for($team)->create();
        $transaction = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);
        $transaction->attachTags([$tag->getKey()]);
        $tag->deactivate();

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.transactions.update', [
                'team' => $team->getRouteKey(),
                'transaction' => $transaction->getKey(),
            ]), transactionPayload([
                $account->getForeignKey() => $account->getKey(),
                $currency->getForeignKey() => $currency->getKey(),
                'tag_ids' => [$tag->getKey()],
            ]))
            ->assertRedirect();

        expect($transaction->tags()->pluck($tag->qualifyColumn($tag->getKeyName()))->all())->toBe([$tag->getKey()]);
    });

    it('allows removing an already-attached inactive tag', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);
        $tag = Tag::factory()->active()->for($team)->create();
        $transaction = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);
        $transaction->attachTags([$tag->getKey()]);
        $tag->deactivate();

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.transactions.update', [
                'team' => $team->getRouteKey(),
                'transaction' => $transaction->getKey(),
            ]), transactionPayload([
                $account->getForeignKey() => $account->getKey(),
                $currency->getForeignKey() => $currency->getKey(),
            ]))
            ->assertRedirect();

        expect($transaction->tags()->count())->toBe(0);
    });

    it('rejects an inactive tag that was not attached', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);
        $tag = Tag::factory()->inactive()->for($team)->create();
        $transaction = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.transactions.update', [
                'team' => $team->getRouteKey(),
                'transaction' => $transaction->getKey(),
            ]), transactionPayload([
                $account->getForeignKey() => $account->getKey(),
                $currency->getForeignKey() => $currency->getKey(),
                'tag_ids' => [$tag->getKey()],
            ]))
            ->assertRedirect()
            ->assertInvalid(['tag_ids.0']);
    });

    it('rejects duplicate tag ids', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);
        $tag = Tag::factory()->active()->for($team)->create();
        $transaction = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.transactions.update', [
                'team' => $team->getRouteKey(),
                'transaction' => $transaction->getKey(),
            ]), transactionPayload([
                $account->getForeignKey() => $account->getKey(),
                $currency->getForeignKey() => $currency->getKey(),
                'tag_ids' => [$tag->getKey(), $tag->getKey()],
            ]))
            ->assertRedirect()
            ->assertInvalid(['tag_ids.0']);
    });

    it('rejects a tag from another team', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);
        $tag = Tag::factory()->active()->for(Team::factory())->create();
        $transaction = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.transactions.update', [
                'team' => $team->getRouteKey(),
                'transaction' => $transaction->getKey(),
            ]), transactionPayload([
                $account->getForeignKey() => $account->getKey(),
                $currency->getForeignKey() => $currency->getKey(),
                'tag_ids' => [$tag->getKey()],
            ]))
            ->assertRedirect()
            ->assertInvalid(['tag_ids.0']);
    });

    it('keeps the stored quote when date and currency are unchanged', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);
        $transaction = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'occurred_at' => '2024-06-01 12:00:00',
            'quote' => '2.5',
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $payload = transactionPayload([
            $account->getForeignKey() => $account->getKey(),
            $currency->getForeignKey() => $currency->getKey(),
            'description' => 'Updated description only',
            'occurred_at' => '2024-06-01 12:00:00',
        ]);
        unset($payload['quote']);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.transactions.update', [
                'team' => $team->getRouteKey(),
                'transaction' => $transaction->getKey(),
            ]), $payload)
            ->assertRedirect();

        expect($transaction->fresh()?->quote)->toBe('2.500000000000000000');
    });

    it('defaults a blank quote from the rate as of a new date', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        Quote::factory()->for($currency)->create([
            'observed_at' => '2024-07-01 00:00:00',
            'rate' => '3',
        ]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);
        $transaction = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'occurred_at' => '2024-06-01 12:00:00',
            'quote' => '2.5',
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $payload = transactionPayload([
            $account->getForeignKey() => $account->getKey(),
            $currency->getForeignKey() => $currency->getKey(),
            'occurred_at' => '2024-07-15 12:00:00',
        ]);
        unset($payload['quote']);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.transactions.update', [
                'team' => $team->getRouteKey(),
                'transaction' => $transaction->getKey(),
            ]), $payload)
            ->assertRedirect();

        expect($transaction->fresh()?->quote)->toBe('3.000000000000000000');
    });

    it('keeps a submitted quote instead of the as-of rate', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);
        $transaction = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.transactions.update', [
                'team' => $team->getRouteKey(),
                'transaction' => $transaction->getKey(),
            ]), transactionPayload([
                $account->getForeignKey() => $account->getKey(),
                $currency->getForeignKey() => $currency->getKey(),
                'quote' => '41.25',
            ]))
            ->assertRedirect();

        expect($transaction->fresh()?->quote)->toBe('41.250000000000000000');
    });

    it('rejects a blank quote when no rate exists as of the new date', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);
        $transaction = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'occurred_at' => '2024-06-01 12:00:00',
            'quote' => '2.5',
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $payload = transactionPayload([
            $account->getForeignKey() => $account->getKey(),
            $currency->getForeignKey() => $currency->getKey(),
            'occurred_at' => '2024-01-01 12:00:00',
        ]);
        unset($payload['quote']);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.transactions.update', [
                'team' => $team->getRouteKey(),
                'transaction' => $transaction->getKey(),
            ]), $payload)
            ->assertRedirect()
            ->assertInvalid(['quote']);
    });

    it('converts a datetime-local occurred_at to UTC using the submitted timezone', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);
        $transaction = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.transactions.update', [
                'team' => $team->getRouteKey(),
                'transaction' => $transaction->getKey(),
            ]), transactionPayload([
                $account->getForeignKey() => $account->getKey(),
                $currency->getForeignKey() => $currency->getKey(),
                'occurred_at' => '2026-09-06T12:21',
                'timezone' => 'America/Argentina/Buenos_Aires',
            ]))
            ->assertRedirect();

        expect($transaction->fresh()?->occurred_at->toDateTimeString())->toBe('2026-09-06 15:21:00');
    });

    it('rejects an account from another team', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $foreign = Account::factory()->bank()->active()->for(Team::factory())->create();
        $foreign->currencies()->attach($currency);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);
        $transaction = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.transactions.update', [
                'team' => $team->getRouteKey(),
                'transaction' => $transaction->getKey(),
            ]), transactionPayload([
                $foreign->getForeignKey() => $foreign->getKey(),
                $currency->getForeignKey() => $currency->getKey(),
                'quote' => '1',
            ]))
            ->assertRedirect()
            ->assertInvalid([$foreign->getForeignKey()]);
    });

    it('rejects a currency that is not attached to the account', function (): void {
        [$user, $team] = actingMember();
        $attached = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $other = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($attached);
        $transaction = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $attached->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.transactions.update', [
                'team' => $team->getRouteKey(),
                'transaction' => $transaction->getKey(),
            ]), transactionPayload([
                $account->getForeignKey() => $account->getKey(),
                $other->getForeignKey() => $other->getKey(),
            ]))
            ->assertRedirect()
            ->assertInvalid([$other->getForeignKey()]);
    });

    it('returns 404 when updating a transaction from another team', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $foreign = Transaction::factory()->create();

        $this->patch(route('finance.teams.transactions.update', [
            'team' => $team->getRouteKey(),
            'transaction' => $foreign->getKey(),
        ]), transactionPayload([
            $currency->getForeignKey() => $currency->getKey(),
        ]))->assertNotFound();
    });

    it('rejects an empty payload', function (): void {
        [$user, $team] = actingMember();
        $transaction = Transaction::factory()->expense()->create(['team_id' => $team->getKey()]);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.transactions.update', [
                'team' => $team->getRouteKey(),
                'transaction' => $transaction->getKey(),
            ]), [])
            ->assertRedirect()
            ->assertInvalid(['amount', 'occurred_at', 'quote', 'type']);
    });

    it('allows keeping the opening balance type on an opening balance row', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency, ['balance' => '100.00']);
        $transaction = Transaction::factory()->openingBalance()->create([
            'account_id' => $account->getKey(),
            'amount' => '100.00',
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.transactions.update', [
                'team' => $team->getRouteKey(),
                'transaction' => $transaction->getKey(),
            ]), transactionPayload([
                $account->getForeignKey() => $account->getKey(),
                $currency->getForeignKey() => $currency->getKey(),
                'amount' => '100.00',
                'type' => TransactionType::OpeningBalance->value,
            ]))
            ->assertRedirect();

        expect($transaction->fresh()?->type)->toBe(TransactionType::OpeningBalance);
    });

    it('rejects opening_balance as a new type on an income row', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency, ['balance' => '100.00']);
        $transaction = Transaction::factory()->income()->create([
            'account_id' => $account->getKey(),
            'amount' => '10.00',
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.transactions.update', [
                'team' => $team->getRouteKey(),
                'transaction' => $transaction->getKey(),
            ]), transactionPayload([
                $account->getForeignKey() => $account->getKey(),
                $currency->getForeignKey() => $currency->getKey(),
                'type' => TransactionType::OpeningBalance->value,
            ]))
            ->assertRedirect()
            ->assertInvalid(['type']);
    });

    it('records only the changed fields and the new tags in the activity log', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency, ['balance' => '100.00']);
        $tag = Tag::factory()->for($team)->create();
        $transaction = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'amount' => '10.50',
            'currency_id' => $currency->getKey(),
            'description' => 'Lunch',
            'occurred_at' => '2024-06-01 12:00:00',
            'quote' => '1',
            'team_id' => $team->getKey(),
        ]);
        clearActivities();

        $this->patch(route('finance.teams.transactions.update', [
            'team' => $team->getRouteKey(),
            'transaction' => $transaction->getKey(),
        ]), transactionPayload([
            $account->getForeignKey() => $account->getKey(),
            $currency->getForeignKey() => $currency->getKey(),
            'description' => 'Dinner',
            'tag_ids' => [$tag->getKey()],
        ]))->assertRedirect();

        $activities = activitiesOf($team);

        expect($activities->pluck('event')->all())->toBe(['updated', 'tags_synced'])
            ->and($activities->pluck('batch_uuid')->unique()->filter()->all())->toHaveCount(1)
            ->and($activities->first()?->causer?->is($user))->toBeTrue()
            ->and($activities->first()?->attribute_changes['old'])->toBe(['description' => 'Lunch'])
            ->and($activities->first()?->attribute_changes['attributes'])->toBe(['description' => 'Dinner'])
            ->and($activities->last()?->attribute_changes['attributes']['tags'])->toBe([$tag->getKey()]);
    });

    it('records no entry when nothing about the transaction changed', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency, ['balance' => '100.00']);
        $transaction = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'amount' => '10.50',
            'currency_id' => $currency->getKey(),
            'description' => 'Lunch',
            'occurred_at' => '2024-06-01 12:00:00',
            'quote' => '1',
            'team_id' => $team->getKey(),
        ]);
        clearActivities();

        $this->patch(route('finance.teams.transactions.update', [
            'team' => $team->getRouteKey(),
            'transaction' => $transaction->getKey(),
        ]), transactionPayload([
            $account->getForeignKey() => $account->getKey(),
            $currency->getForeignKey() => $currency->getKey(),
        ]))->assertRedirect();

        expect(activitiesOf($team))->toHaveCount(0);
    });
});

describe('destroy', function (): void {
    it('deletes the transaction', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create();
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency, ['balance' => '75.00']);
        $transaction = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'amount' => '25.00',
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->delete(route('finance.teams.transactions.destroy', [
                'team' => $team->getRouteKey(),
                'transaction' => $transaction->getKey(),
            ]))
            ->assertRedirect();

        assertFlashedToast(trans('Deleted'));
        $this->assertModelMissing($transaction);
        expect((float) $account->currencies()->firstOrFail()->pivot->balance)->toBe(100.0);
    });

    it('refuses to edit, update, or delete a transaction owned by a transfer', function (string $method, string $route, bool $withPayload): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency, ['balance' => '100.00']);
        $transfer = Transfer::factory()->for($team)->create();
        $transaction = Transaction::factory()->transferOut()->create([
            'account_id' => $account->getKey(),
            'amount' => '25.00',
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'transfer_id' => $transfer->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->{$method}(route($route, [
                'team' => $team->getRouteKey(),
                'transaction' => $transaction->getKey(),
            ]), $withPayload ? transactionPayload([
                $account->getForeignKey() => $account->getKey(),
                $currency->getForeignKey() => $currency->getKey(),
            ]) : [])
            ->assertForbidden();

        $this->assertModelExists($transaction);
    })->with([
        'edit' => ['get', 'finance.teams.transactions.edit', false],
        'update' => ['patch', 'finance.teams.transactions.update', true],
        'destroy' => ['delete', 'finance.teams.transactions.destroy', false],
    ]);

    it('refuses to delete a transfer fee row through the transaction routes', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency, ['balance' => '100.00']);
        $transfer = Transfer::factory()->for($team)->create();
        $fee = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'amount' => '1.50',
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'transfer_id' => $transfer->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->delete(route('finance.teams.transactions.destroy', [
                'team' => $team->getRouteKey(),
                'transaction' => $fee->getKey(),
            ]))
            ->assertForbidden();

        $this->assertModelExists($fee);
    });

    it('refuses to delete a transaction older than the free window', function (): void {
        [$user, $team] = actingMember();
        $account = Account::factory()->bank()->active()->for($team)->create();
        $older = Transaction::factory()->expense()->for($account)->for($team)->create([
            'occurred_at' => CarbonImmutable::now()->startOfMonth()->subMonths(5)->subDay(),
        ]);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->delete(route('finance.teams.transactions.destroy', [
                'team' => $team->getRouteKey(),
                'transaction' => $older->getKey(),
            ]))
            ->assertNotFound();

        $this->assertModelExists($older);
    });

    it('reports a hidden transfer leg as not found rather than forbidden', function (): void {
        [$user, $team] = actingMember();
        $account = Account::factory()->bank()->active()->for($team)->create();
        $transfer = Transfer::factory()->for($team)->create();
        $leg = Transaction::factory()->transferOut()->for($account)->for($team)->for($transfer)->create([
            'occurred_at' => CarbonImmutable::now()->startOfMonth()->subMonths(5)->subDay(),
        ]);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->delete(route('finance.teams.transactions.destroy', [
                'team' => $team->getRouteKey(),
                'transaction' => $leg->getKey(),
            ]))
            ->assertNotFound();
    });

    it('deletes a transaction older than the free window for a pro team', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        $currency = Currency::factory()->active()->fiat()->create();
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency, ['balance' => '75.00']);
        $older = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'amount' => '25.00',
            'currency_id' => $currency->getKey(),
            'occurred_at' => CarbonImmutable::now()->subYears(2),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getRouteKey()]))
            ->delete(route('finance.teams.transactions.destroy', [
                'team' => $team->getRouteKey(),
                'transaction' => $older->getKey(),
            ]))
            ->assertRedirect();

        $this->assertModelMissing($older);
    });

    it('records the deletion with the last values of the transaction', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create();
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency, ['balance' => '75.00']);
        $transaction = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'amount' => '25.00',
            'currency_id' => $currency->getKey(),
            'description' => 'Lunch',
            'team_id' => $team->getKey(),
        ]);
        clearActivities();

        $this->delete(route('finance.teams.transactions.destroy', [
            'team' => $team->getRouteKey(),
            'transaction' => $transaction->getKey(),
        ]))->assertRedirect();

        $activity = activitiesOf($team, 'deleted')->sole();

        expect($activity->causer?->is($user))->toBeTrue()
            ->and($activity->subject_id)->toBe($transaction->getKey())
            ->and($activity->attribute_changes['old']['description'])->toBe('Lunch')
            ->and($activity->attribute_changes['old']['amount'])->toBe($transaction->amount)
            ->and($activity->attribute_changes)->not->toHaveKey('attributes');
    });
});
