<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Transaction;

use App\Modules\Core\Finance\Http\Requests\Team\Transaction\Concerns\ResolvesTransactionInputs;
use App\Modules\Core\Finance\Http\Requests\Team\Transaction\Concerns\ValidatesTransactionInputs;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Support\Helpers\Amount;
use App\Modules\Support\Helpers\Instant;
use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    use ResolvesTransactionInputs;
    use ValidatesTransactionInputs;

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
        if ($this->boolean('repeat')) {
            $this->merge($this->repeatStartFrom($localOccurredAt));
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
            ...$this->transactionRules($team, $this->all()),
            ...$this->repeatRules($this->all()),
        ];
    }
}
