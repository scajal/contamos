<?php

declare(strict_types=1);

namespace App\Modules\Administration\Http\Controllers;

use App\Modules\Administration\Http\Requests\User\IndexRequest;
use App\Modules\Core\Identity\Models\User;
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
}
