<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Transaction;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Catalog\Models\Currency\Quote;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use DateTimeZone;
use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    /**
     * Get the account for the transaction.
     */
    protected function account(Team $team): ?Account
    {
        /** @var \App\Modules\Core\Finance\Models\Team\Account|null $account */
        $account = Account::query()
            ->where(new Account()->team()->getForeignKeyName(), $team->getKey())
            ->find($this->input(new Transaction()->account()->getForeignKeyName()));

        return $account;
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // @todo: Add authorization logic.
        return true;
    }

    /**
     * Get the selected currency, if any.
     */
    protected function currency(): ?Currency
    {
        /** @var \App\Modules\Core\Catalog\Models\Currency|null $currency */
        $currency = Currency::query()
            ->find($this->input(new Transaction()->currency()->getForeignKeyName()));

        return $currency;
    }

    /**
     * Convert a timezone-naive datetime-local value to UTC.
     */
    protected function occurredAtInUtc(): ?string
    {
        // Get the occurred at and timezone values from the request.
        /** @var string|null $occurredAt */
        $occurredAt = $this->input('occurred_at');

        /** @var string|null $timezone */
        $timezone = $this->input('timezone');

        // If the occurred at or timezone is not a string, return null.
        if (! is_string($occurredAt) || ! is_string($timezone)) {
            return null;
        }

        // Validate the timezone.
        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            return null;
        }

        // Validate the occurred at format.
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?$/', $occurredAt) !== 1) {
            return null;
        }

        // Parse the occurred at value and convert to UTC.
        try {
            return CarbonImmutable::parse($occurredAt, $timezone)->utc()->toIso8601String();
        } catch (InvalidFormatException) {
            return null;
        }
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'occurred_at' => $occurredAt = $this->occurredAtInUtc() ?? $this->input('occurred_at'),
            'quote' => ($quote = $this->input('quote')) ? $quote : $this->quoteAsOfOccurredAt($occurredAt)?->rate,
        ]);
    }

    /**
     * Get the latest quote observed at or before the transaction instant.
     */
    protected function quoteAsOfOccurredAt(mixed $occurredAt): ?Quote
    {
        // If the occurred at is not a string or is an empty string, return null.
        if (! is_string($occurredAt) || $occurredAt === '') {
            return null;
        }

        // Parse the occurred at value and convert to UTC.
        try {
            $at = CarbonImmutable::parse($occurredAt);
        } catch (InvalidFormatException) {
            return null;
        }

        // Get the quote for the currency at the occurred at value.
        return $this->currency()?->quoteAsOf($at);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(#[RouteParameter('team')] Team $team): array
    {
        return [
            new Transaction()->account()->getForeignKeyName() => [
                'required',
                'string',
                Rule::exists(Account::class, new Account()->getKeyName())
                    ->where(new Account()->team()->getForeignKeyName(), $team->getKey()),
            ],
            'amount' => [
                'required',
                (($currency = $this->currency()) instanceof \App\Modules\Core\Catalog\Models\Currency) ? "decimal:0,{$currency->decimal_places}" : 'decimal:0,18',
                'gt:0',
            ],
            new Transaction()->currency()->getForeignKeyName() => [
                'required',
                'string',
                Rule::exists(Currency::class, new Currency()->getKeyName()),
                Rule::in($this->account($team)?->currencies->pluck(new Currency()->getKeyName()) ?? []),
            ],
            'description' => [
                'sometimes',
                'nullable',
                'string',
            ],
            'occurred_at' => [
                'required',
                'date',
            ],
            'quote' => [
                'required',
                'decimal:0,18',
            ],
            'tag_ids' => [
                'sometimes',
                'array',
            ],
            'tag_ids.*' => [
                'required',
                'string',
                'distinct',
                Rule::exists(Tag::class, new Tag()->getKeyName())
                    ->where(new Tag()->team()->getForeignKeyName(), $team->getKey())
                    ->where('is_active', true),
            ],
            'type' => [
                'required',
                'string',
                'max:255',
                Rule::enum(TransactionType::class),
            ],
        ];
    }

    /**
     * Get the validated tag ids for the transaction.
     *
     * @return array<int, string>
     */
    public function tagIds(): array
    {
        $tagIds = $this->safe(['tag_ids'])['tag_ids'] ?? [];

        if (! is_array($tagIds)) {
            return [];
        }

        return array_values(array_filter($tagIds, is_string(...)));
    }
}
