<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Controllers\Team;

use App\Modules\Core\Finance\Http\Requests\Team\Tag\IndexRequest;
use App\Modules\Core\Finance\Http\Requests\Team\Tag\StoreRequest;
use App\Modules\Core\Finance\Http\Requests\Team\Tag\UpdateRequest;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Support\Objects\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use InertiaUI\Modal\Modal;
use Spatie\QueryBuilder\QueryBuilder;

class TagController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Team $team, IndexRequest $request): Response
    {
        return Inertia::render('finance/teams/tags/index', [
            'filters' => $request->validated('filter', []),
            'tags' => Inertia::defer(
                fn (): LengthAwarePaginator => QueryBuilder::for($team->tags(), $request)
                    ->allowedFilters(...$request->filters())
                    ->orderBy('name')
                    ->paginate(10)
                    ->withQueryString(),
            ),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Team $team): Modal
    {
        return Inertia::modal('finance/teams/tags/create')
            ->baseRoute('finance.teams.tags.index', ['team' => $team->getKey()]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Team $team, StoreRequest $request): RedirectResponse
    {
        // Create the tag for the team.
        $team->tags()->create($request->safe(['name']));

        // Show a success message.
        Toast::make(trans('Saved'))
            ->flash();

        return back();
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Team $team, Tag $tag): Modal
    {
        return Inertia::modal('finance/teams/tags/edit', [
            'tag' => $tag,
        ])->baseRoute('finance.teams.tags.index', ['team' => $team->getKey()]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Team $team, Tag $tag, UpdateRequest $request): RedirectResponse
    {
        // Update the tag.
        $tag->update($request->safe(['name']));

        // Show a success message.
        Toast::make(trans('Saved'))
            ->flash();

        return back();
    }

    /**
     * Activate the specified resource.
     */
    public function activate(Team $team, Tag $tag): RedirectResponse
    {
        // Activate the tag.
        $tag->activate();

        // Show a success message.
        Toast::make(trans('Saved'))
            ->flash();

        return back();
    }

    /**
     * Deactivate the specified resource.
     */
    public function deactivate(Team $team, Tag $tag): RedirectResponse
    {
        // Deactivate the tag.
        $tag->deactivate();

        // Show a success message.
        Toast::make(trans('Saved'))
            ->flash();

        return back();
    }
}
