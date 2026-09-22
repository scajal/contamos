<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Transaction;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\RecurringRule\Frequency;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Http\Requests\Team\Transaction\Concerns\ResolvesTransactionInputs;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Support\Helpers\Amount;
use App\Modules\Support\Helpers\Instant;
use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    use ResolvesTransactionInputs;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // @todo: Add authorization logic.
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $localOccurredAt = $this->input('occurred_at');

        $this->merge([
            'amount' => new Amount()->normalize($this->input('amount')),
            'quote' => new Amount()->normalize($this->input('quote')),
        ]);

        $this->merge([
            'occurred_at' => $occurredAt = Instant::toUtc($localOccurredAt, $this->input('timezone')) ?? $localOccurredAt,
            'quote' => ($quote = $this->input('quote')) ? $quote : Instant::resolveQuote($this->currency(), $occurredAt)?->rate,
        ]);

        // A repeating transaction's schedule starts from the same
        // wall-clock date and time the transaction itself occurred at,
        // read before it was converted to UTC.
        if ($this->boolean('repeat') && is_string($localOccurredAt) && preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})/', $localOccurredAt, $matches) === 1) {
            $this->merge([
                'recurring_starts_on' => $matches[1],
                'recurring_time_of_day' => "{$matches[2]}:00",
            ]);
        }
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
            'category' => $this->categoryRules(),
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
            'repeat' => [
                'sometimes',
                'boolean',
            ],
            'recurring_ends_on' => [
                'nullable',
                'date',
                'after_or_equal:recurring_starts_on',
            ],
            'recurring_frequency' => [
                Rule::requiredIf($this->boolean('repeat')),
                Rule::in(array_column(Frequency::cases(), 'value')),
            ],
            'recurring_interval' => [
                Rule::requiredIf($this->boolean('repeat')),
                'integer',
                'between:1,99',
            ],
            'recurring_starts_on' => [
                Rule::requiredIf($this->boolean('repeat')),
                'date',
            ],
            'recurring_time_of_day' => [
                Rule::requiredIf($this->boolean('repeat')),
                'date_format:H:i:s',
            ],
            'timezone' => [
                function (string $attribute, mixed $value, \Closure $fail): void {
                    // Only a repeating transaction needs a valid timezone: the
                    // rule's schedule is computed in it. A non-repeating entry
                    // tolerates a missing or invalid one, falling back to the
                    // occurred_at value as submitted.
                    if ($this->boolean('repeat') && ! Instant::isZone($value)) {
                        $fail(trans('The timezone is invalid.'));
                    }
                },
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
                Rule::in(array_column(TransactionType::recordableCases(), 'value')),
            ],
        ];
    }

    /**
     * Get the recurring rule attributes when the request asks the
     * transaction to repeat, or null otherwise.
     *
     * @return array<string, mixed>|null
     */
    public function repeatAttributes(): ?array
    {
        if (! $this->boolean('repeat')) {
            return null;
        }

        return [
            ...$this->safe([
                new Transaction()->account()->getForeignKeyName(),
                'amount',
                'category',
                new Transaction()->currency()->getForeignKeyName(),
                'description',
                'type',
            ]),
            'ends_on' => $this->input('recurring_ends_on'),
            'frequency' => $this->input('recurring_frequency'),
            'interval' => $this->input('recurring_interval'),
            'starts_on' => $this->input('recurring_starts_on'),
            'time_of_day' => $this->input('recurring_time_of_day'),
            'timezone' => $this->input('timezone'),
        ];
    }
}
