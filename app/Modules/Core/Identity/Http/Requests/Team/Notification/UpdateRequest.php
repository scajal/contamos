<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Http\Requests\Team\Notification;

use App\Modules\Core\Identity\Enums\Team\NotificationType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request: one
     * switch per notification type, every one of them sent.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [];

        foreach (NotificationType::cases() as $type) {
            $rules[$type->value] = [
                'required',
                'boolean',
            ];
        }

        return $rules;
    }
}
