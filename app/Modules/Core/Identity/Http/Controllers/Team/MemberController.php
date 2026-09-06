<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Http\Controllers\Team;

use App\Modules\Core\Identity\Http\Requests\Team\Member\IndexRequest;
use App\Modules\Core\Identity\Models\Team;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\QueryBuilder;

class MemberController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Team $team, IndexRequest $request): Response
    {
        return Inertia::render('identity/teams/members/index', [
            'filters' => $request->validated('filter', []),
            'members' => Inertia::defer(
                fn (): LengthAwarePaginator => QueryBuilder::for($team->users(), $request)
                    ->allowedFilters(...$request->filters())
                    ->orderBy('name')
                    ->paginate(10)
                    ->withQueryString(),
            ),
        ]);
    }
}
