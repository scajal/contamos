<?php

declare(strict_types=1);

namespace App\Modules\Administration\Http\Controllers;

use App\Modules\Administration\Http\Requests\User\IndexRequest;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Objects\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\QueryBuilder;

class UserController
{
    /**
     * Display a listing of the resource.
     */
    public function index(IndexRequest $request): Response
    {
        return Inertia::render('administration/identity/users/index', [
            'filters' => $request->validated('filter', []),
            'users' => Inertia::defer(
                fn (): LengthAwarePaginator => QueryBuilder::for(User::class, $request)
                    ->allowedFilters(...$request->filters())
                    ->orderBy('created_at', 'desc')
                    ->paginate(10)
                    ->withQueryString(),
            ),
        ]);
    }

    /**
     * Activate the specified resource.
     */
    public function activate(User $user): RedirectResponse
    {
        // Activate the user.
        $user->update(['is_active' => true]);

        // Show a message to the user.
        Toast::make(trans('Saved'))
            ->flash();

        return back();
    }

    /**
     * Deactivate the specified resource.
     */
    public function deactivate(User $user): RedirectResponse
    {
        // Deactivate the user.
        $user->update(['is_active' => false]);

        // Show a message to the user.
        Toast::make(trans('Saved'))
            ->flash();

        return back();
    }
}
