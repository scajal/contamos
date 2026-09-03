<?php

declare(strict_types=1);

namespace App\Modules\Core\Catalog\Http\Requests\Currency\Quote;

use App\Modules\Core\Catalog\Enums\Currency\Quote\QuoteSource;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
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
            'source' => QuoteSource::ManualOrScheduled->value,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'observed_at' => [
                'required',
                'date',
            ],
            'rate' => [
                'required',
                'numeric',
                'gte:0',
            ],
            'source' => [
                'required',
                'string',
                Rule::enum(QuoteSource::class),
            ],
        ];
    }
}
