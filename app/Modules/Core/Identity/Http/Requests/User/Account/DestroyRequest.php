<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Http\Requests\User\Account;

use App\Modules\Core\Identity\Models\User;
use Closure;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DestroyRequest extends FormRequest
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
    public function rules(#[CurrentUser] User $user): array
    {
        return [
            'email' => [
                'required',
                'string',
                $this->matchesUser($user),
            ],
        ];
    }

    /**
     * The typed address must be the user's own, so a tampered
     * request cannot get past the confirmation.
     */
    protected function matchesUser(User $user): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($user): void {
            if ($value !== $user->email) {
                $fail(trans('The email does not match your account.'));
            }
        };
    }
}
