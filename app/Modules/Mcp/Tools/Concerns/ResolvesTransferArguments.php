<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Concerns;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Http\Requests\Team\Transfer\Concerns\DefinesTransferRules;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Finance\Models\Team\Transfer;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Exceptions\ToolError;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;

/**
 * Turns the arguments of the MCP transfer write tools into the
 * attributes the transfer Actions take, then validates them with
 * the same rules as the web transfer requests. On an update, every
 * argument left out keeps the transfer's current value.
 *
 * @mixin \Laravel\Mcp\Server\Tool
 * @mixin \App\Modules\Mcp\Tools\Concerns\InteractsWithTeams
 * @mixin \App\Modules\Mcp\Tools\Concerns\NormalizesInputs
 * @mixin \App\Modules\Mcp\Tools\Concerns\PresentsRecords
 */
trait ResolvesTransferArguments
{
    use DefinesTransferRules;

    /**
     * Get the schema of the arguments that describe a transfer.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    protected function transferArgumentsSchema(JsonSchema $schema): array
    {
        return [
            'origin_account' => $schema->string()
                ->description('The id of the account the money leaves, as listed by `list_accounts`.'),
            'destination_account' => $schema->string()
                ->description('The id of the account the money arrives at. It must differ from `origin_account`.'),
            'amount_sent' => $schema->string()
                ->description('The positive amount that leaves the origin account, as a decimal string such as "250.00", without the fee.'),
            'amount_received' => $schema->string()
                ->description('The positive amount that arrives at the destination account, without the fee. Defaults to `amount_sent` when both sides use the same currency.'),
            'origin_currency' => $schema->string()
                ->description('The ISO code of the origin account\'s currency the money leaves in, such as "USD". Inferred when the account holds one currency.'),
            'destination_currency' => $schema->string()
                ->description("The ISO code of the destination account's currency the money arrives in. Inferred when the account holds one currency."),
            'origin_fee' => $schema->string()->nullable()
                ->description('A positive fee charged on the origin account, in the origin currency, recorded as an expense. Null for none.'),
            'destination_fee' => $schema->string()->nullable()
                ->description('A positive fee charged on the destination account, in the destination currency, recorded as an expense. Null for none.'),
            'origin_quote' => $schema->string()
                ->description('The exchange rate of the origin currency. Leave out to use the rate recorded for the date.'),
            'destination_quote' => $schema->string()
                ->description('The exchange rate of the destination currency. Leave out to use the rate recorded for the date.'),
            ...$this->occurredAtSchema($schema),
            ...$this->timezoneSchema($schema),
            'description' => $schema->string()->nullable()
                ->description('A short note about the transfer.'),
            'tags' => $schema->array()->items($schema->string())
                ->description('Tags of the team, by id or exact name, as listed by `list_tags`. Tags are never created.'),
        ];
    }

    /**
     * Resolve the arguments into the validated attributes of the
     * transfer and the ids of its tags. Given the transfer being
     * edited, each argument left out keeps its current value.
     *
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function transferAttributes(Request $request, Team $team, ?Transfer $transfer = null): array
    {
        $arguments = $request->all();

        $given = fn (string $key): bool => ! $transfer instanceof \App\Modules\Core\Finance\Models\Team\Transfer || array_key_exists($key, $arguments);

        $transfer?->loadMissing('tags', 'transactions.account', 'transactions.currency');

        $originLeg = $transfer?->originLeg();
        $destinationLeg = $transfer?->destinationLeg();

        // Resolve the account on each side.
        $originAccount = $given('origin_account') || ! $originLeg instanceof Transaction
            ? $this->findTransferAccount($team, $request->get('origin_account'), 'origin account')
            : $originLeg->account;

        $destinationAccount = $given('destination_account') || ! $destinationLeg instanceof Transaction
            ? $this->findTransferAccount($team, $request->get('destination_account'), 'destination account')
            : $destinationLeg->account;

        // Resolve the currency of each side among its account's.
        $originCurrency = $this->transferSideCurrency($originAccount, $originLeg, $given('origin_currency'), $request->get('origin_currency'), 'origin_currency');
        $destinationCurrency = $this->transferSideCurrency($destinationAccount, $destinationLeg, $given('destination_currency'), $request->get('destination_currency'), 'destination_currency');

        // Resolve when it happened, in UTC.
        $currentOccurredAt = $transfer?->occurred_at->utc()->toIso8601String();

        $occurredAt = $given('occurred_at') || $currentOccurredAt === null
            ? $this->occurredAt($request->get('occurred_at'), $request->get('timezone'))
            : $currentOccurredAt;

        // Resolve the amounts; the amount received mirrors the
        // amount sent when both sides share the currency.
        $amountSent = $given('amount_sent') || ! $originLeg instanceof Transaction
            ? $this->transferDecimal($request->get('amount_sent'))
            : $this->amount($originLeg->amount, $originLeg->decimal_places);

        $amountReceived = match (true) {
            array_key_exists('amount_received', $arguments) => $this->transferDecimal($request->get('amount_received')),
            $originCurrency->is($destinationCurrency) => $amountSent,
            $destinationLeg instanceof Transaction => $this->amount($destinationLeg->amount, $destinationLeg->decimal_places),
            default => null,
        };

        // Resolve the exchange rate of each side.
        $originQuote = $this->transferSideQuote($originCurrency, $occurredAt, $originLeg, $currentOccurredAt, $request->get('origin_quote'), 'origin_quote');
        $destinationQuote = $this->transferSideQuote($destinationCurrency, $occurredAt, $destinationLeg, $currentOccurredAt, $request->get('destination_quote'), 'destination_quote');

        // Resolve the fee of each side; null charges none.
        $originFee = $given('origin_fee')
            ? $this->transferDecimal($request->get('origin_fee'))
            : $this->transferFeeAmount($transfer?->originFee());

        $destinationFee = $given('destination_fee')
            ? $this->transferDecimal($request->get('destination_fee'))
            : $this->transferFeeAmount($transfer?->destinationFee());

        // Resolve the tags by id or name, keeping the ones the
        // transfer already carries allowed.
        $attachedTagIds = $transfer?->tags->pluck(new Tag()->getKeyName())->filter(fn (mixed $value): bool => is_string($value))->values()->all() ?? [];

        $tagIds = $given('tags')
            ? $this->tagIds($team, $request->get('tags'), $attachedTagIds)
            : $attachedTagIds;

        $attributes = [
            'amount_received' => $amountReceived,
            'amount_sent' => $amountSent,
            'destination_account_id' => $destinationAccount->getKey(),
            'destination_currency_id' => $destinationCurrency->getKey(),
            'destination_fee' => $destinationFee,
            'destination_quote' => $destinationQuote,
            'occurred_at' => $occurredAt,
            'origin_account_id' => $originAccount->getKey(),
            'origin_currency_id' => $originCurrency->getKey(),
            'origin_fee' => $originFee,
            'origin_quote' => $originQuote,
            'tag_ids' => $tagIds,
        ];

        // A description left out of an update is left alone.
        if ($given('description')) {
            $attributes['description'] = $request->get('description');
        }

        // Validate with the same rules as the web requests.
        $validated = Validator::make($attributes, $this->transferRules($team, $attributes, $transfer))->validate();

        return [$this->validatedOnly($validated, array_keys(Arr::except($attributes, ['tag_ids']))), $tagIds];
    }

    /**
     * Find one of the team's accounts by its id, refusing with a
     * "not found" that names the side otherwise.
     */
    protected function findTransferAccount(Team $team, mixed $accountId, string $label): Account
    {
        /** @var \App\Modules\Core\Finance\Models\Team\Account $account */
        $account = $this->findOrFail($team->accounts(), $accountId, $label);

        return $account;
    }

    /**
     * Resolve the currency of one side of the transfer: the code
     * given, or the leg's current one while it stays on the same
     * account, or the account's only currency.
     */
    protected function transferSideCurrency(Account $account, ?Transaction $leg, bool $given, mixed $code, string $attribute): Currency
    {
        // A code given, or a side that moved, is resolved anew.
        if ($given || ! $leg instanceof Transaction || $leg->account_id !== $account->getKey()) {
            return $this->accountCurrency($account, $given ? $code : null, $attribute);
        }

        // Otherwise the leg keeps its currency.
        return $leg->currency;
    }

    /**
     * Resolve the exchange rate of one side of the transfer: the
     * rate given, the leg's stored one while its currency and date
     * are unchanged, or the rate recorded for the date.
     */
    protected function transferSideQuote(Currency $currency, string $occurredAt, ?Transaction $leg, ?string $currentOccurredAt, mixed $quote, string $attribute): string
    {
        $isGiven = (is_string($quote) && Str::trim($quote) !== '') || is_int($quote) || is_float($quote);

        // An unchanged side keeps the rate it was recorded with.
        if (! $isGiven && $leg instanceof Transaction && $leg->currency_id === $currency->getKey() && $currentOccurredAt === $occurredAt) {
            return (string) $leg->quote;
        }

        // Otherwise the rate given, or the one as of the date,
        // naming this side's argument when none is recorded.
        try {
            return $this->quote($currency, $occurredAt, $quote);
        } catch (ToolError $toolError) {
            throw new ToolError(Str::replace('`quote`', "`{$attribute}`", $toolError->getMessage()), $toolError->getCode(), $toolError);
        }
    }

    /**
     * Get the amount of a fee the transfer currently charges, or
     * null when it charges none.
     */
    protected function transferFeeAmount(?Transaction $fee): ?string
    {
        return $fee instanceof Transaction ? $this->amount($fee->amount, $fee->decimal_places) : null;
    }

    /**
     * Turn a figure the assistant sent as a number into a
     * decimal string; anything else is left for the rules.
     */
    protected function transferDecimal(mixed $value): mixed
    {
        return match (true) {
            is_int($value), is_float($value) => (string) $value,
            is_string($value) => Str::trim($value),
            default => $value,
        };
    }
}
