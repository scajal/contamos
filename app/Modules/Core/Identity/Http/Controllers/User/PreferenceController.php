<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Http\Controllers\User;

use App\Modules\Core\Identity\Http\Requests\User\Preference\UpdateRequest;
use App\Modules\Core\Identity\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cookie;

class PreferenceController
{
    /**
     * Store the language or colour scheme the member chose from
     * a menu, so it follows them to every device.
     */
    public function update(UpdateRequest $request, #[CurrentUser] User $user): Response
    {
        $key = $request->preferenceKey();
        $value = $request->preferenceValue();

        // Store the choice on the account.
        $user->rememberPreference($key, $value);

        // Set the cookie too, so this browser and the marketing
        // pages behave as they did before the member signed in.
        Cookie::queue($key->cookie($value));

        // The menus apply the choice themselves, so nothing is
        // sent back but the cookie.
        return response()->noContent();
    }
}
