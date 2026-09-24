<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Transfer;

use App\Modules\Core\Finance\Http\Requests\Team\Transfer\Concerns\ResolvesTransferInputs;
use App\Modules\Core\Identity\Models\Team;
use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    use ResolvesTransferInputs;

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
        return $this->transferRules($team, $this->all());
    }
}
