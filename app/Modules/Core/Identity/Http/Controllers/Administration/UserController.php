<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Http\Controllers\Administration;

use App\Modules\Core\Identity\Http\Requests\Administration\User\IndexRequest;
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
        return Inertia::render('identity/administration/users/index', [
            'users' => Inertia::defer(
                fn (): LengthAwarePaginator => QueryBuilder::for(User::class, $request)
                    ->allowedFilters(...$request->filters())
                    ->orderBy('created_at', 'desc')
                    ->paginate(10)
                    ->withQueryString(),
            ),
            'filters' => $request->validated('filter', []),
        ]);
    }
}
