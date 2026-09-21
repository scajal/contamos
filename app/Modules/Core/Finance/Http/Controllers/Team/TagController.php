<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Controllers\Team;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Finance\Actions\Team\CreateTag;
use App\Modules\Core\Finance\Actions\Team\UpdateTag;
use App\Modules\Core\Finance\Http\Requests\Team\Tag\IndexRequest;
use App\Modules\Core\Finance\Http\Requests\Team\Tag\StoreRequest;
use App\Modules\Core\Finance\Http\Requests\Team\Tag\UpdateRequest;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Objects\Toast;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
        return Inertia::modal('finance/teams/tags/create', [
            'currencies' => $this->budgetCurrencies($team),
        ])->baseRoute('finance.teams.tags.index', ['team' => $team->getRouteKey()]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Team $team, StoreRequest $request, CreateTag $createTag, #[CurrentUser] User $user): RedirectResponse
    {
        // Create the tag for the team, opening a budget from
        // this month for every currency an amount came in for.
        $createTag->for($team)
            ->by($user)
            ->using($request->safe(['name']), $request->budgets($team));

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
            'budgets' => $this->runningAmounts($team, $tag),
            'currencies' => $this->budgetCurrencies($team),
            'tag' => $tag,
        ])->baseRoute('finance.teams.tags.index', ['team' => $team->getRouteKey()]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Team $team, Tag $tag, UpdateRequest $request, UpdateTag $updateTag, #[CurrentUser] User $user): RedirectResponse
    {
        // Rename the tag and move its running budget amounts,
        // leaving past months on the amount that applied.
        $updateTag->for($team)
            ->by($user)
            ->using($tag, $request->safe(['name']), $request->budgets($team));

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

    /**
     * The currencies a budget may be set in from the tag form,
     * or none at all when the team's plan has no budgets.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Catalog\Models\Currency>
     */
    protected function budgetCurrencies(Team $team): EloquentCollection
    {
        return Feature::ManageBudgets->enabled($team)
            ? $team->heldCurrencies()
            : new EloquentCollection;
    }

    /**
     * The amount the tag is budgeted at this month in each
     * currency, so the form opens on what is in force.
     *
     * @return array<string, string>
     */
    protected function runningAmounts(Team $team, Tag $tag): array
    {
        if (! Feature::ManageBudgets->enabled($team)) {
            return [];
        }

        $currencyKey = new BudgetPeriod()->currency()->getForeignKeyName();

        return $tag->budgetPeriods()
            ->covering(CarbonImmutable::now())
            ->get()
            ->mapWithKeys(function (BudgetPeriod $period) use ($currencyKey): array {
                $currencyId = $period->getAttribute($currencyKey);

                return is_string($currencyId) ? [$currencyId => (string) $period->amount] : [];
            })
            ->all();
    }
}
