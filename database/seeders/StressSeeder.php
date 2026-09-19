<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Core\Catalog\Enums\Currency\CurrencyType;
use App\Modules\Core\Catalog\Enums\Currency\Quote\QuoteSource;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Account\AccountType;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\AccountCurrency;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\Team\TeamInvitation;
use App\Modules\Core\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class StressSeeder extends Seeder
{
    /**
     * Create a stress seeder.
     */
    public function __construct(
        public int $accountCount = 24,
        public int $memberCount = 6,
        public int $tagCount = 35,
        public int $transactionCount = 8000,
        public int $invitationCount = 4,
        public ?string $email = null,
    ) {}

    /**
     * Seed a large, realistic dataset onto an existing user's current team.
     */
    public function run(): void
    {
        $user = $this->user();
        $team = $this->teamFor($user);
        $currencies = $this->currencies();

        $this->ensureQuotes($currencies);
        $this->ensureExistingAccountsHaveACurrency($team, $currencies);
        $members = $this->members($team);
        $this->invitations($team, $user);
        $this->accounts($team, $currencies);
        $tags = $this->tags($team);
        $transactionIds = $this->transactions($team, $user, $members, $currencies);
        $this->taggables($transactionIds, $tags);
        $this->recalculateBalances($team);
    }

    /**
     * Create additional accounts for the team and attach currencies.
     *
     * @param  \Illuminate\Support\Collection<string, \App\Modules\Core\Catalog\Models\Currency>  $currencies
     */
    protected function accounts(Team $team, Collection $currencies): void
    {
        $uyu = $currencies->get('UYU');
        $usd = $currencies->get('USD');
        $eur = $currencies->get('EUR');
        $usdt = $currencies->get('USDT');

        if (! $uyu instanceof Currency) {
            throw new RuntimeException('UYU must exist before accounts can be seeded.');
        }

        $names = $this->paddedNames($this->accountNames(), $this->accountCount);

        foreach ($names as $index => $name) {
            $type = match ($index % 7) {
                0, 1, 2, 3 => AccountType::Bank,
                4, 5 => AccountType::Cash,
                default => AccountType::Crypto,
            };

            $account = Account::factory()->for($team)->create([
                'is_active' => $index % 11 !== 0,
                'name' => $name,
                'type' => $type,
            ]);

            $currencyIds = match ($type) {
                AccountType::Crypto => array_values(array_filter([
                    $this->optionalKey($usdt),
                    $this->optionalKey($usd),
                    $this->stringKey($uyu),
                ])),
                AccountType::Cash => array_values(array_filter([
                    $this->stringKey($uyu),
                    $index % 2 === 0 ? $this->optionalKey($usd) : null,
                ])),
                AccountType::Bank => array_values(array_filter([
                    $this->stringKey($uyu),
                    $this->optionalKey($usd),
                    $index % 3 === 0 ? $this->optionalKey($eur) : null,
                ])),
            };

            $account->currencies()->syncWithoutDetaching(array_values(array_unique($currencyIds)));
        }
    }

    /**
     * Realistic account names used before numbering extras.
     *
     * @return list<string>
     */
    protected function accountNames(): array
    {
        return [
            'BROU',
            'Itaú',
            'Santander',
            'Scotiabank',
            'Heritage',
            'HSBC',
            'Mercado Pago',
            'Prex',
            'Midinero',
            'Prex USD',
            'Efectivo casa',
            'Efectivo trabajo',
            'Caja chica',
            'Ahorro vacaciones',
            'Ahorro aguinaldo',
            'Binance',
            'Lemon',
            'Galicia',
            'BBVA',
            'N26 viaje',
            'Wise',
            'PayPal',
            'Cuenta sueldo',
            'BROU caja de ahorro con un nombre extra largo para ver cómo se corta en la lista',
        ];
    }

    /**
     * Ensure catalog currencies exist and return them keyed by code.
     *
     * @return \Illuminate\Support\Collection<string, \App\Modules\Core\Catalog\Models\Currency>
     */
    protected function currencies(): Collection
    {
        $currencies = collect();

        foreach ($this->currencyCatalog() as $definition) {
            $currency = Currency::query()->firstOrCreate(
                ['code' => $definition['code']],
                [
                    'decimal_places' => $definition['decimal_places'],
                    'is_active' => true,
                    'name' => $definition['name'],
                    'quote_source' => QuoteSource::ManualOrScheduled,
                    'symbol' => $definition['symbol'],
                    'type' => $definition['type'],
                ],
            );

            $currencies->put($currency->code, $currency);
        }

        /** @var \Illuminate\Support\Collection<string, \App\Modules\Core\Catalog\Models\Currency> $currencies */
        return $currencies;
    }

    /**
     * Catalog of currencies to first-or-create, with a quote in UYU.
     *
     * @return list<array{code: string, decimal_places: int, name: string, quote: string, symbol: string, type: \App\Modules\Core\Catalog\Enums\Currency\CurrencyType}>
     */
    protected function currencyCatalog(): array
    {
        return [
            ['code' => 'UYU', 'decimal_places' => 2, 'name' => 'Peso Uruguayo', 'quote' => '1', 'symbol' => '$', 'type' => CurrencyType::Fiat],
            ['code' => 'USD', 'decimal_places' => 2, 'name' => 'Dólar estadounidense', 'quote' => '40.25', 'symbol' => 'US$', 'type' => CurrencyType::Fiat],
            ['code' => 'EUR', 'decimal_places' => 2, 'name' => 'Euro', 'quote' => '43.80', 'symbol' => '€', 'type' => CurrencyType::Fiat],
            ['code' => 'BRL', 'decimal_places' => 2, 'name' => 'Real brasileño', 'quote' => '7.40', 'symbol' => 'R$', 'type' => CurrencyType::Fiat],
            ['code' => 'ARS', 'decimal_places' => 2, 'name' => 'Peso argentino', 'quote' => '0.042', 'symbol' => 'AR$', 'type' => CurrencyType::Fiat],
            ['code' => 'USDT', 'decimal_places' => 6, 'name' => 'Tether', 'quote' => '40.20', 'symbol' => 'USDT', 'type' => CurrencyType::Cryptocurrency],
        ];
    }

    /**
     * Attach UYU to team accounts that have no currencies yet.
     *
     * @param  \Illuminate\Support\Collection<string, \App\Modules\Core\Catalog\Models\Currency>  $currencies
     */
    protected function ensureExistingAccountsHaveACurrency(Team $team, Collection $currencies): void
    {
        $uyu = $currencies->get('UYU');

        if (! $uyu instanceof Currency) {
            return;
        }

        $team->accounts()
            ->whereDoesntHave('currencies')
            ->get()
            ->each(fn (Account $account): array => $account->currencies()->syncWithoutDetaching([$this->stringKey($uyu)]));
    }

    /**
     * Create a current quote for each currency that does not have one.
     *
     * @param  \Illuminate\Support\Collection<string, \App\Modules\Core\Catalog\Models\Currency>  $currencies
     */
    protected function ensureQuotes(Collection $currencies): void
    {
        $quotes = collect($this->currencyCatalog())->keyBy('code');

        foreach ($currencies as $currency) {
            if ($currency->currentQuote instanceof \App\Modules\Core\Catalog\Models\Currency\Quote) {
                continue;
            }

            $definition = $quotes->get($currency->code);

            if (! is_array($definition)) {
                continue;
            }

            $currency->quotes()->create([
                'observed_at' => CarbonImmutable::now()->subYear(),
                'rate' => $definition['quote'],
                'source' => QuoteSource::ManualOrScheduled,
            ]);

            $currency->quotes()->create([
                'observed_at' => CarbonImmutable::now(),
                'rate' => $definition['quote'],
                'source' => QuoteSource::ManualOrScheduled,
            ]);
        }
    }

    /**
     * Create pending and expired invitations for the team.
     */
    protected function invitations(Team $team, User $user): void
    {
        $count = $this->invitationCount;
        $pending = max(0, $count - 1);

        if ($pending > 0) {
            TeamInvitation::factory()
                ->count($pending)
                ->for($team)
                ->for($user, 'inviter')
                ->create();
        }

        if ($count > 0) {
            TeamInvitation::factory()
                ->expired()
                ->for($team)
                ->for($user, 'inviter')
                ->create();
        }
    }

    /**
     * Create additional members for the team.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Identity\Models\User>
     */
    protected function members(Team $team): EloquentCollection
    {
        $count = $this->memberCount;

        if ($count > 0) {
            $members = User::factory()->count($count)->create();
            $members->last()?->deactivate();
            $team->users()->syncWithoutDetaching($members->mapWithKeys(fn (User $member): array => [
                $this->stringKey($member) => ['role' => Role::Admin],
            ])->all());
        }

        return $team->users()->get();
    }

    /**
     * Return the model key when the model exists.
     */
    protected function optionalKey(?Model $model): ?string
    {
        return $model instanceof Model ? $this->stringKey($model) : null;
    }

    /**
     * Repeat the last name with a suffix when more records are requested than names.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    protected function paddedNames(array $names, int $count): array
    {
        if ($count <= count($names)) {
            return array_slice($names, 0, $count);
        }

        $padded = $names;

        for ($index = count($names); $index < $count; $index++) {
            $padded[] = $names[$index % count($names)].' '.($index + 1);
        }

        return $padded;
    }

    /**
     * Recalculate each account-currency pivot balance from the team's transactions.
     */
    protected function recalculateBalances(Team $team): void
    {
        $transaction = new Transaction;
        $accountFk = $transaction->account()->getForeignKeyName();
        $currencyFk = $transaction->currency()->getForeignKeyName();
        $typeColumn = $transaction->qualifyColumn('type');

        $team->accounts()
            ->with('currencies')
            ->get()
            ->each(function (Account $account) use ($accountFk, $currencyFk, $typeColumn, $transaction): void {
                foreach ($account->currencies as $currency) {
                    $income = $transaction->newQuery()
                        ->where($accountFk, $this->stringKey($account))
                        ->where($currencyFk, $this->stringKey($currency))
                        ->where($typeColumn, TransactionType::Income)
                        ->sum('amount');
                    $expense = $transaction->newQuery()
                        ->where($accountFk, $this->stringKey($account))
                        ->where($currencyFk, $this->stringKey($currency))
                        ->where($typeColumn, TransactionType::Expense)
                        ->sum('amount');

                    $account->currencies()->updateExistingPivot($this->stringKey($currency), [
                        'balance' => bcsub((string) $income, (string) $expense, 18),
                    ]);
                }
            });
    }

    /**
     * Return the string primary key of a ULID model.
     */
    protected function stringKey(Model $model): string
    {
        $key = $model->getKey();

        if (! is_string($key)) {
            throw new RuntimeException(sprintf('Expected a string key for %s.', $model::class));
        }

        return $key;
    }

    /**
     * Attach tags to a subset of the inserted transactions.
     *
     * @param  list<string>  $transactionIds
     * @param  \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Finance\Models\Team\Tag>  $tags
     */
    protected function taggables(array $transactionIds, EloquentCollection $tags): void
    {
        if ($transactionIds === [] || $tags->isEmpty()) {
            return;
        }

        $tagKeys = $tags->map(fn (Tag $tag): string => $this->stringKey($tag))->all();
        $morphClass = (new Transaction)->getMorphClass();
        $table = (new Transaction)->tags()->getTable();
        $rows = [];

        foreach ($transactionIds as $index => $transactionId) {
            if ($index % 5 === 0) {
                continue;
            }

            $assigned = collect(fake()->randomElements($tagKeys, fake()->numberBetween(1, min(3, count($tagKeys)))))
                ->filter(fn (mixed $tagId): bool => is_string($tagId))
                ->unique()
                ->values();

            foreach ($assigned as $tagId) {

                $rows[] = [
                    (new Tag)->getForeignKey() => $tagId,
                    'taggable_id' => $transactionId,
                    'taggable_type' => $morphClass,
                ];
            }

            if (count($rows) >= 200) {
                DB::table($table)->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table($table)->insert($rows);
        }
    }

    /**
     * Create tags for the team, skipping names that already exist.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Finance\Models\Team\Tag>
     */
    protected function tags(Team $team): EloquentCollection
    {
        $names = $this->paddedNames($this->tagNames(), $this->tagCount);
        $teamFk = (new Tag)->team()->getForeignKeyName();

        foreach ($names as $index => $name) {
            Tag::query()->firstOrCreate(
                [
                    $teamFk => $this->stringKey($team),
                    'name' => $name,
                ],
                [
                    'is_active' => $index % 13 !== 0,
                ],
            );
        }

        return $team->tags()->get();
    }

    /**
     * Realistic tag names used before numbering extras.
     *
     * @return list<string>
     */
    protected function tagNames(): array
    {
        return [
            'Comida',
            'Supermercado',
            'Transporte',
            'Alquiler',
            'Servicios',
            'UTE',
            'Antel',
            'OSE',
            'Salud',
            'Farmacia',
            'Salidas',
            'Ropa',
            'Educación',
            'Viajes',
            'Sueldo',
            'Freelance',
            'Aguinaldo',
            'Ahorro',
            'Transferencia',
            'Regalos',
            'Mascotas',
            'Hogar',
            'Impuestos',
            'Seguros',
            'Deporte',
            'Streaming',
            'Café',
            'Delivery',
            'Nafta',
            'Mantenimiento',
            'Suscripciones',
            'Inversiones',
            'Criptomonedas',
            'Oficina',
            'Nombre de etiqueta extra largo para probar el desbordamiento en filtros y chips',
        ];
    }

    /**
     * Resolve the team that should receive the stress data.
     */
    protected function teamFor(User $user): Team
    {
        $team = $user->currentTeam ?? $user->teams()->first();

        if (! $team instanceof Team) {
            throw new RuntimeException('The user has no team. Create a team first, then run this seeder.');
        }

        return $team;
    }

    /**
     * Insert a large batch of transactions and return their keys.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Identity\Models\User>  $members
     * @param  \Illuminate\Support\Collection<string, \App\Modules\Core\Catalog\Models\Currency>  $currencies
     * @return list<string>
     */
    protected function transactions(Team $team, User $owner, EloquentCollection $members, Collection $currencies): array
    {
        $count = $this->transactionCount;

        if ($count === 0) {
            return [];
        }

        $pairs = AccountCurrency::query()
            ->whereIn((new AccountCurrency)->account()->getForeignKeyName(), $team->accounts()->select((new Account)->getQualifiedKeyName()))
            ->get();

        if ($pairs->isEmpty()) {
            throw new RuntimeException('The team has no account currencies to attach transactions to.');
        }

        $quotes = collect($this->currencyCatalog())->mapWithKeys(
            fn (array $definition): array => [$definition['code'] => $definition['quote']],
        );
        $currenciesByKey = $currencies->keyBy(fn (Currency $currency): string => $this->stringKey($currency));
        $memberKeys = $members->map(fn (User $member): string => $this->stringKey($member))->all();
        $ownerKey = $this->stringKey($owner);
        $teamKey = $this->stringKey($team);
        $now = CarbonImmutable::now();
        $descriptions = $this->transactionDescriptions();
        $ids = [];
        $rows = [];

        $accountFk = (new Transaction)->account()->getForeignKeyName();
        $currencyFk = (new Transaction)->currency()->getForeignKeyName();
        $teamFk = (new Transaction)->team()->getForeignKeyName();
        $userFk = (new Transaction)->user()->getForeignKeyName();

        for ($index = 0; $index < $count; $index++) {
            /** @var \App\Modules\Core\Finance\Models\Team\AccountCurrency $pair */
            $pair = $pairs->random();
            $currencyKey = $pair->getAttribute($currencyFk);
            $accountKey = $pair->getAttribute($accountFk);

            if (! is_string($currencyKey) || ! is_string($accountKey)) {
                continue;
            }

            $currency = $currenciesByKey->get($currencyKey);
            $decimalPlaces = $currency instanceof Currency ? $currency->decimal_places : 2;
            $code = $currency instanceof Currency ? $currency->code : 'UYU';
            $type = $index % 5 === 0 ? TransactionType::Income : TransactionType::Expense;
            $id = (string) Str::ulid();
            $occurredAt = $now
                ->subDays(fake()->numberBetween(0, 730))
                ->subMinutes(fake()->numberBetween(0, 1439));

            $ids[] = $id;
            $rows[] = [
                (new Transaction)->getKeyName() => $id,
                $accountFk => $accountKey,
                'amount' => $this->transactionAmount($decimalPlaces, $type),
                $currencyFk => $currencyKey,
                'decimal_places' => $decimalPlaces,
                'description' => $index === 0
                    ? 'Compra enorme en el supermercado con una descripción larga para ver cómo se comporta el detalle, el listado con infinite scroll y el truncado en móvil cuando el texto no para de crecer y sigue y sigue.'
                    : fake()->randomElement($descriptions),
                'occurred_at' => $occurredAt->format('Y-m-d H:i:s'),
                'quote' => is_string($quote = $quotes->get($code)) ? $quote : '1',
                $teamFk => $teamKey,
                'type' => $type->value,
                $userFk => $index % 17 === 0 ? null : fake()->randomElement([$ownerKey, ...$memberKeys]),
                'created_at' => $now->format('Y-m-d H:i:s'),
                'updated_at' => $now->format('Y-m-d H:i:s'),
            ];

            if (count($rows) === 50) {
                Transaction::query()->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            Transaction::query()->insert($rows);
        }

        return $ids;
    }

    /**
     * Format a realistic amount for the currency's decimal places.
     */
    protected function transactionAmount(int $decimalPlaces, TransactionType $type): string
    {
        $max = $type === TransactionType::Income ? 80_000 : 12_000;
        $min = $type === TransactionType::Income ? 8_000 : 20;

        return number_format(fake()->randomFloat(min($decimalPlaces, 2), $min, $max), $decimalPlaces, '.', '');
    }

    /**
     * Short transaction descriptions mixed across everyday categories.
     *
     * @return list<string|null>
     */
    protected function transactionDescriptions(): array
    {
        return [
            'Supermercado Disco',
            'Tienda Inglesa',
            'Devoto',
            'Farmashop',
            'UTE',
            'Antel fibra',
            'OSE',
            'Alquiler',
            'Nafta Ancap',
            'Uber',
            'Cabify',
            'PedidosYa',
            'Rappi',
            'Café',
            'Almuerzo',
            'Cine Movie',
            'Netflix',
            'Spotify',
            'Sueldo',
            'Freelance diseño',
            'Aguinaldo',
            'Transferencia a Prex',
            'Cambio de dólares',
            'Regalo cumpleaños',
            'Veterinaria',
            'Gimnasio',
            'Corte de pelo',
            'Farmacia',
            'Impuesto DGI',
            'Seguro auto',
            'Mantenimiento',
            'Compra Binance',
            null,
        ];
    }

    /**
     * Resolve the user that owns the dataset.
     */
    protected function user(): User
    {
        $email = $this->email;

        if (is_string($email) && $email !== '') {
            $user = User::query()->where('email', $email)->first();

            if (! $user instanceof User) {
                throw new RuntimeException("No user found with email [{$email}].");
            }

            return $user;
        }

        $user = User::query()->first();

        if (! $user instanceof User) {
            throw new RuntimeException('Stress seeding requires an existing user. Log in once, then run this seeder.');
        }

        return $user;
    }
}
