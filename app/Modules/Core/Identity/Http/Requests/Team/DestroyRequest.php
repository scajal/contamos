<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Http\Requests\Team;

use App\Modules\Core\Identity\Models\Team;
use Closure;
use Illuminate\Container\Attributes\RouteParameter;
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
    public function rules(#[RouteParameter('team')] Team $team): array
    {
        return [
            'slug' => [
                'required',
                'string',
                $this->matchesTeam($team),
            ],
        ];
    }

    /**
     * The typed handle must be the team's own, so the deletion is
     * deliberate.
     */
    protected function matchesTeam(Team $team): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($team): void {
            if ($value !== $team->slug) {
                $fail(trans('The handle does not match the team.'));
            }
        };
    }
}
