<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests\Team\Subscription;

use App\Modules\Billing\Enums\Feature;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpgradeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'feature' => [
                'sometimes',
                'nullable',
                Rule::enum(Feature::class),
            ],
        ];
    }

    /**
     * Get the feature the user tried to use, if any.
     */
    public function feature(): ?Feature
    {
        $feature = $this->validated('feature');

        return is_string($feature) ? Feature::from($feature) : null;
    }
}
