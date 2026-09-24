<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Concerns;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Account\AccountType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\AccountCurrency;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;

/**
 * Turns the account arguments an assistant sends, with currencies
 * as ISO codes, into the attributes the account Actions take,
 * validated by the same rules as the web account forms, and
 * presents an account with its balances.
 *
 * Requires the using tool to also use
 * \App\Modules\Core\Finance\Http\Requests\Team\Account\Concerns\DefinesAccountRules,
 * \App\Modules\Mcp\Tools\Concerns\NormalizesInputs and
 * \App\Modules\Mcp\Tools\Concerns\PresentsRecords.
 *
 * @mixin \Laravel\Mcp\Server\Tool
 */
trait InteractsWithAccounts
{
    /**
     * Get the schema of the arguments describing an account.
     * They are required on creation and optional on update,
     * where a missing one keeps the account's current value.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    protected function accountSchema(JsonSchema $schema, bool $required): array
    {
        return [
            'name' => $schema->string()
                ->description('The account name, up to 255 characters.')
                ->required($required),
            'type' => $schema->string()
                ->enum(array_column(AccountType::cases(), 'value'))
                ->description('The account type. A crypto account holds only cryptocurrencies; bank and cash accounts hold only fiat currencies.')
                ->required($required),
            'currencies' => $schema->array()
                ->items($schema->string())
                ->description('The ISO codes of every currency the account holds, such as ["USD", "UYU"]. At least one.'.($required ? '' : ' Replaces the current list; a currency left out is removed.'))
                ->required($required),
            'opening_balances' => $schema->array()
                ->items($schema->object([
                    'currency' => $schema->string()->description('The ISO code of one of `currencies`.')->required(),
                    'amount' => $schema->string()->description('The starting balance as a decimal string, such as "1500.00". Zero or more, with at most the currency\'s decimal places.')->required(),
                    'quote' => $schema->string()->description('The exchange rate to record it with. Leave out to use the rate recorded for `occurred_at`.'),
                ]))
                ->description($required
                    ? 'Optional starting balances, one per currency.'
                    : 'Optional starting balances, only for currencies this update adds to the account.'),
            ...$this->occurredAtSchema($schema),
            ...$this->timezoneSchema($schema),
        ];
    }

    /**
     * Validate the account arguments against the web's account
     * rules and get what the account Actions take. On update, a
     * missing argument keeps the account's current value and
     * opening balances apply only to newly added currencies.
     *
     * @return array{attributes: array{name: string, type: string}, currency_ids: array<int, string>, opening_balances: array<string, \App\Modules\Core\Finance\Objects\OpeningBalance>}
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validatedAccount(Request $request, ?Account $account = null): array
    {
        // Missing arguments keep the account's current values.
        $name = $request->get('name', $account?->name);
        $type = $request->get('type', $account?->type->value);
        $codes = $request->get('currencies', $account?->currencies->pluck('code')->all());

        if (! is_array($codes)) {
            throw ValidationException::withMessages(['currencies' => 'The currencies must be a list of ISO codes.']);
        }

        // Map every ISO code to its currency id; an unknown code
        // is passed on as is, so the shared rules reject it.
        $currencies = Currency::query()
            ->whereIn('code', collect($codes)->filter(fn (mixed $value): bool => is_string($value))->map(fn (string $code): string => Str::upper(Str::trim($code)))->all())
            ->get()
            ->keyBy(fn (Currency $currency): string => Str::upper($currency->code));

        $currencyIds = [];
        $attributes = ['currency_ids' => 'currencies'];

        foreach (array_values($codes) as $index => $code) {
            $key = is_string($code) ? Str::upper(Str::trim($code)) : '';
            $currency = $currencies->get($key);

            $currencyIds[] = $currency instanceof Currency ? (string) $currency->getKey() : $key;
            $attributes["currency_ids.{$index}"] = "currency {$key}";
        }

        // Only the currencies being added take a balance: all of
        // them on creation, the new ones on update.
        $attachedIds = $account?->currencies->pluck(new Currency()->getKeyName())->filter(fn (mixed $value): bool => is_string($value))->all() ?? [];
        $balanceCurrencyIds = array_values(array_diff($currencyIds, $attachedIds));

        // Key the opening balances and rates by currency id.
        [$balances, $quotes] = $this->openingBalanceInputs($request->get('opening_balances'), $currencies->all(), $balanceCurrencyIds, $attributes);

        // The instant the balances are recorded at.
        $occurredAt = $request->get('occurred_at') !== null
            ? $this->occurredAt($request->get('occurred_at'), $request->get('timezone'))
            : CarbonImmutable::now()->toIso8601String();

        // Run the same rules as the web account forms.
        $data = array_filter([
            'currency_ids' => $currencyIds,
            'name' => $name,
            'occurred_at' => $occurredAt,
            'opening_balances' => $balances,
            'quotes' => $quotes,
            'type' => $type,
        ], fn (mixed $value): bool => $value !== null && $value !== []);

        $rules = $this->accountRules(is_string($type) ? AccountType::tryFrom($type) : null, $balanceCurrencyIds, $quotes, $occurredAt);

        /** @var array{currency_ids: array<int, string>, name: string, type: string} $validated */
        $validated = Validator::validate($data, $rules, [], $attributes);

        return [
            'attributes' => ['name' => $validated['name'], 'type' => $validated['type']],
            'currency_ids' => array_values($validated['currency_ids']),
            'opening_balances' => $this->resolveOpeningBalances($balances, $quotes, $occurredAt),
        ];
    }

    /**
     * Key the opening balance entries, given by ISO code, by
     * currency id, refusing one for a currency that does not
     * take a balance.
     *
     * @param  array<string, \App\Modules\Core\Catalog\Models\Currency>  $currencies
     * @param  array<int, string>  $balanceCurrencyIds
     * @param  array<string, string>  $attributes
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function openingBalanceInputs(mixed $entries, array $currencies, array $balanceCurrencyIds, array &$attributes): array
    {
        // No opening balances is a valid answer.
        if ($entries === null) {
            return [[], []];
        }

        if (! is_array($entries)) {
            throw ValidationException::withMessages(['opening_balances' => 'The opening balances must be a list of {currency, amount, quote} objects.']);
        }

        $balances = [];
        $quotes = [];

        foreach (array_values($entries) as $index => $entry) {
            $code = is_array($entry) && is_string($entry['currency'] ?? null) ? Str::upper(Str::trim($entry['currency'])) : '';
            $currency = $currencies[$code] ?? null;

            // The currency must be one the account is taking on.
            if (! $currency instanceof Currency || ! in_array($currency->getKey(), $balanceCurrencyIds, true)) {
                throw ValidationException::withMessages(["opening_balances.{$index}.currency" => "An opening balance for \"{$code}\" needs that currency in `currencies`, and only a currency the account does not hold yet takes one."]);
            }

            /** @var array<string, mixed> $entry */
            $currencyId = $currency->getKey();
            $balances[$currencyId] = $entry['amount'] ?? null;
            $attributes["opening_balances.{$currencyId}"] = "opening balance for {$code}";

            if (($entry['quote'] ?? null) !== null) {
                $quotes[$currencyId] = $entry['quote'];
                $attributes["quotes.{$currencyId}"] = "quote for {$code}";
            }
        }

        return [$balances, $quotes];
    }

    /**
     * Present an account with its type, active flag, and the
     * balance it holds in each currency.
     *
     * @return array<string, mixed>
     */
    protected function presentAccount(Account $account): array
    {
        return [
            'id' => $account->getKey(),
            'name' => $account->name,
            'type' => $account->type->value,
            'is_active' => $account->is_active,
            'currencies' => $account->currencies
                ->sortBy('code')
                ->map(fn (Currency $currency): array => [
                    'code' => $currency->code,
                    'name' => $currency->name,
                    'balance' => $this->amount(($pivot = $currency->getRelation('pivot')) instanceof AccountCurrency ? $pivot->balance : '0', $currency->decimal_places),
                ])
                ->values()
                ->all(),
        ];
    }
}
