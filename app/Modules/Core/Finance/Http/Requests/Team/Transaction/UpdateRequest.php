<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Transaction;

use App\Modules\Core\Finance\Http\Requests\Team\Transaction\Concerns\ResolvesTransactionInputs;
use App\Modules\Core\Finance\Http\Requests\Team\Transaction\Concerns\ValidatesTransactionInputs;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Support\Helpers\Amount;
use App\Modules\Support\Helpers\Instant;
use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
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
        $this->merge([
            'amount' => new Amount()->normalize($this->input('amount')),
            'quote' => new Amount()->normalize($this->input('quote')),
        ]);

        $this->merge([
            'occurred_at' => $occurredAt = Instant::toUtc($this->input('occurred_at'), $this->input('timezone')) ?? $this->input('occurred_at'),
            'quote' => ($quote = $this->input('quote')) ? $quote : Instant::resolveQuote($this->currency(), $occurredAt)?->rate,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(#[RouteParameter('team')] Team $team, #[RouteParameter('transaction')] Transaction $transaction): array
    {
        return $this->transactionRules($team, $this->all(), $transaction);
    }
}
