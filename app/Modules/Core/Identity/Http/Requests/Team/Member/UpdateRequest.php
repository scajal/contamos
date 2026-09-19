<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Http\Requests\Team\Member;

use App\Modules\Core\Identity\Enums\Team\Role;
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
            'role' => [
                'required',
                Rule::enum(Role::class)->only(Role::assignable()),
            ],
        ];
    }
}
