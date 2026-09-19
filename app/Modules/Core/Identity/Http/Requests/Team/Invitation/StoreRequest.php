<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Http\Requests\Team\Invitation;

use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Closure;
use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(#[RouteParameter('team')] Team $team): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                $this->notMember($team),
                $this->notInvited($team),
            ],
            'role' => [
                'required',
                Rule::enum(Role::class)->only(Role::assignableBy($team)),
            ],
        ];
    }

    /**
     * Reject an email that already belongs to a member of the team.
     */
    protected function notMember(Team $team): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($team): void {
            // Validate that the value is a string.
            if (! is_string($value)) {
                return;
            }

            // If a member already uses the email, fail the validation.
            $isMember = $team->users()
                ->where((new User)->qualifyColumn('email'), $value)
                ->exists();

            if ($isMember) {
                $fail(trans('This email already belongs to a member of the team.'));
            }
        };
    }

    /**
     * Reject an email that already has an open invitation to the team.
     */
    protected function notInvited(Team $team): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($team): void {
            // Validate that the value is a string.
            if (! is_string($value)) {
                return;
            }

            // If an open invitation exists for the email, fail the validation.
            $isInvited = $team->invitations()
                ->pending()
                ->where('email', Str::lower($value))
                ->exists();

            if ($isInvited) {
                $fail(trans('This email already has an open invitation.'));
            }
        };
    }
}
