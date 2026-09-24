<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Http\Requests\User\Preference;

use App\Modules\Core\Identity\Enums\User\PreferenceKey;
use Closure;
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
        // A member may only change their own preferences, which
        // the controller reads off the signed-in user.
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
            'key' => [
                'required',
                Rule::enum(PreferenceKey::class),
            ],
            'value' => [
                'required',
                'string',
                $this->acceptedByKey(),
            ],
        ];
    }

    /**
     * Get the preference the request changes.
     */
    public function preferenceKey(): PreferenceKey
    {
        /** @var string $key */
        $key = $this->validated('key');

        return PreferenceKey::from($key);
    }

    /**
     * Get the value the request stores for the preference.
     */
    public function preferenceValue(): string
    {
        /** @var string $value */
        $value = $this->validated('value');

        return $value;
    }

    /**
     * Refuse a value the requested preference does not accept.
     * An unknown key is left to its own rule.
     */
    protected function acceptedByKey(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $key = PreferenceKey::tryFrom(is_string($this->input('key')) ? $this->input('key') : '');

            if ($key instanceof PreferenceKey && ! $key->accepts($value)) {
                $fail(trans('validation.in'));
            }
        };
    }
}
