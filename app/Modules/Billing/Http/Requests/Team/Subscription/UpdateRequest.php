<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests\Team\Subscription;

use App\Modules\Billing\Enums\Subscription\BillingInterval;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'interval' => [
                'required',
                'string',
                Rule::enum(BillingInterval::class),
            ],
        ];
    }
}
