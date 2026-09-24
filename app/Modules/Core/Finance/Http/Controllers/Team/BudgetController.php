<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Controllers\Team;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Finance\Actions\Team\CreateBudgetPeriod;
use App\Modules\Core\Finance\Actions\Team\DeleteBudgetPeriod;
use App\Modules\Core\Finance\Actions\Team\UpdateBudgetPeriod;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Http\Controllers\Team\Concerns\OffersMovementCategories;
use App\Modules\Core\Finance\Http\Requests\Team\Budget\StoreRequest;
use App\Modules\Core\Finance\Http\Requests\Team\Budget\UpdateRequest;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Reports\Team\Concerns\ReportsBudgetProgress;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Objects\Toast;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use InertiaUI\Modal\Modal;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class BudgetController
{
    use OffersMovementCategories;
    use ReportsBudgetProgress;

    /**
     * The month format a budget's bounds travel in.
     */
    protected const string MONTH_FORMAT = 'Y-m';

    /**
     * Display a listing of the resource.
     */
    public function index(Team $team): Response
    {
        // A plan without budgets keeps the ones it recorded while it
        // had them, so the page is locked rather than emptied: the
        // listing still travels and the page blurs it behind the
        // upgrade panel.
        $isLocked = ! Feature::ManageBudgets->enabled($team);

        return Inertia::render('finance/teams/budgets/index', [
            'budgets' => Inertia::defer(
                fn (): LengthAwarePaginator => $this->listing($team),
            ),
            'categories' => $this->categoryChoices(),
            'currencies' => $team->heldCurrencies(),
            'is_locked' => $isLocked,
            'month' => CarbonImmutable::now()->format(self::MONTH_FORMAT),
            'tags' => $team->tags()->orderBy('name')->get(),
            'team' => $team,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Team $team): Modal
    {
        return Inertia::modal('finance/teams/budgets/create', [
            'categories' => $this->categoryChoices(),
            'currencies' => $team->heldCurrencies(),
            'month' => CarbonImmutable::now()->format(self::MONTH_FORMAT),
            'tags' => $team->tags()->orderBy('name')->get(),
        ])->baseRoute('finance.teams.budgets.index', ['team' => $team->getRouteKey()]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Team $team, StoreRequest $request, CreateBudgetPeriod $createBudgetPeriod, #[CurrentUser] User $user): RedirectResponse
    {
        // Open the budget period on the category or the tag the
        // form chose, whichever kind it asked for.
        $createBudgetPeriod->for($team)
            ->targeting($this->submittedTarget($team, $request))
            ->by($user)
            ->using($request->safe(['amount', new BudgetPeriod()->currency()->getForeignKeyName(), 'from_month', 'until_month']));

        // Show a success message.
        Toast::make(trans('Saved'))
            ->flash();

        return back();
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Team $team, BudgetPeriod $budget): Modal
    {
        return Inertia::modal('finance/teams/budgets/edit', [
            'budget' => $this->row($budget, CarbonImmutable::now(), new Collection, false),
            'currencies' => $team->heldCurrencies(),
        ])->baseRoute('finance.teams.budgets.index', ['team' => $team->getRouteKey()]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Team $team, BudgetPeriod $budget, UpdateRequest $request, UpdateBudgetPeriod $updateBudgetPeriod, #[CurrentUser] User $user): RedirectResponse
    {
        // Change the period's amount, currency or months.
        $updateBudgetPeriod->for($team)
            ->by($user)
            ->using($budget, $request->safe(['amount', $budget->currency()->getForeignKeyName(), 'from_month', 'until_month']));

        // Show a success message.
        Toast::make(trans('Saved'))
            ->flash();

        return back();
    }

    /**
     * Stop the specified resource at the end of the current
     * month, so this month keeps its budget and the next
     * one has none.
     */
    public function pause(Team $team, BudgetPeriod $budget, UpdateBudgetPeriod $updateBudgetPeriod, #[CurrentUser] User $user): RedirectResponse
    {
        // Only a running budget with no last month can stop.
        abort_unless($this->canPauseBudget($team, $budget), HttpResponse::HTTP_CONFLICT);

        // Close the stretch on the current month.
        $updateBudgetPeriod->for($team)
            ->by($user)
            ->pause($budget);

        // Show a success message.
        Toast::make(trans('Saved'))
            ->flash();

        return back();
    }

    /**
     * Pick the specified resource's budget back up from the
     * current month, on the amount it was stopped at.
     */
    public function resume(Team $team, BudgetPeriod $budget, CreateBudgetPeriod $createBudgetPeriod, #[CurrentUser] User $user): RedirectResponse
    {
        // Only a paused budget can be picked back up.
        abort_unless($this->canResumeBudget($team, $budget), HttpResponse::HTTP_CONFLICT);

        // Open a fresh unbounded stretch from this month, on
        // whatever the stopped one followed.
        $createBudgetPeriod->for($team)
            ->by($user)
            ->resume($budget);

        // Show a success message.
        Toast::make(trans('Saved'))
            ->flash();

        return back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Team $team, BudgetPeriod $budget, DeleteBudgetPeriod $deleteBudgetPeriod): RedirectResponse
    {
        // Remove the period outright.
        $deleteBudgetPeriod->for($team)
            ->using($budget);

        // Show a success message.
        Toast::make(trans('Deleted'))
            ->flash();

        return back();
    }

    /**
     * The team's budget periods, categories first and newest
     * stretch first within each target, with what the
     * current month has made of the one in force.
     *
     * @return \Illuminate\Pagination\LengthAwarePaginator<int, array<string, mixed>>
     */
    protected function listing(Team $team): LengthAwarePaginator
    {
        $period = new BudgetPeriod;
        $tag = new Tag;
        $month = CarbonImmutable::now();

        /** @var \Illuminate\Pagination\LengthAwarePaginator<int, \App\Modules\Core\Finance\Models\Team\BudgetPeriod> $paginator */
        $paginator = $team->budgets()
            ->with(['currency', 'tag'])
            ->orderBy($period->qualifyColumn('category'))
            // A tag budget reads under the tag's name, which the
            // period only knows the key of.
            ->orderBy(Tag::query()
                ->select('name')
                ->whereColumn($tag->getQualifiedKeyName(), $period->qualifyColumn($period->tag()->getForeignKeyName())))
            ->orderByDesc($period->qualifyColumn('from_month'))
            ->paginate(10)
            ->withQueryString();

        $totals = $this->budgetedTotalsByKey($team, $month);
        $successors = $this->successorMonths($team);

        return $paginator->through(fn (BudgetPeriod $budget): array => $this->row(
            $budget,
            $month,
            $totals,
            $this->isSuperseded($budget, $successors),
        ));
    }

    /**
     * One budget row for the page: the stretch itself, what it
     * follows, where it sits relative to the current month,
     * and how the month is going against it when it is
     * the one in force.
     *
     * @param  \Illuminate\Support\Collection<string, array{expenses: numeric-string, income: numeric-string}>  $totals
     * @return array<string, mixed>
     */
    protected function row(BudgetPeriod $budget, CarbonImmutable $month, Collection $totals, bool $isSuperseded): array
    {
        return [
            'amount' => $budget->amount,
            'currency' => $budget->currency->only([$budget->currency->getKeyName(), 'code', 'decimal_places', 'symbol']),
            'from_month' => $budget->from_month->format(self::MONTH_FORMAT),
            'id' => $budget->getKey(),
            'progress' => $this->monthProgress($budget, $month, $totals),
            'status' => $budget->status($month, $isSuperseded),
            'target' => $this->target($budget),
            'until_month' => $budget->until_month?->format(self::MONTH_FORMAT),
        ];
    }

    /**
     * What a budget follows, as the page reads it: a category
     * with the icon and label it renders, or the tag.
     *
     * @return array<string, mixed>
     */
    protected function target(BudgetPeriod $budget): array
    {
        $target = $budget->target();

        // A category carries no row of its own, so the label
        // and the icon travel with it.
        if ($target instanceof Category) {
            return [
                'category' => [
                    'icon' => $target->icon(),
                    'label' => $target->getLabel(),
                    'value' => $target->value,
                ],
                'kind' => $budget->targetKind(),
            ];
        }

        return [
            'kind' => $budget->targetKind(),
            'tag' => $target?->only([$target->getKeyName(), 'is_active', 'name']),
        ];
    }

    /**
     * The category or tag the create form chose, resolved from
     * the payload the request has already validated.
     */
    protected function submittedTarget(Team $team, StoreRequest $request): Category|Tag
    {
        $category = $request->validated('category');

        // Exactly one of the two travels, which the request
        // has already made sure of.
        if (is_string($category)) {
            return Category::from($category);
        }

        /** @var \App\Modules\Core\Finance\Models\Team\Tag $tag */
        $tag = $team->tags()->findOrFail($request->validated(new BudgetPeriod()->tag()->getForeignKeyName()));

        return $tag;
    }
}
