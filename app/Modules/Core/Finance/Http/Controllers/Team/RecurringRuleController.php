<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Controllers\Team;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Finance\Actions\Team\CreateRecurringRule;
use App\Modules\Core\Finance\Actions\Team\DeleteRecurringRule;
use App\Modules\Core\Finance\Actions\Team\UpdateRecurringRule;
use App\Modules\Core\Finance\Http\Controllers\Team\Concerns\OffersMovementCategories;
use App\Modules\Core\Finance\Http\Requests\Team\RecurringRule\StoreRequest;
use App\Modules\Core\Finance\Http\Requests\Team\RecurringRule\UpdateRequest;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Objects\Toast;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use InertiaUI\Modal\Modal;

class RecurringRuleController
{
    use OffersMovementCategories;

    /**
     * How many rules a page of the listing holds.
     */
    protected const int PER_PAGE = 10;

    /**
     * Display a listing of the resource.
     */
    public function index(Team $team, Request $request): Response
    {
        return Inertia::render('finance/teams/recurring-rules/index', [
            // The rules a Free team keeps are listed read-only, so
            // nothing is lost while the plan cannot generate them.
            'canRecur' => Feature::RecurTransactions->enabled($team),
            'rules' => Inertia::defer(fn (): LengthAwarePaginator => $this->paginateByNextDueDate($team, $request)),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Team $team): Modal
    {
        return Inertia::modal('finance/teams/recurring-rules/create', $this->form($team))
            ->baseRoute('finance.teams.recurring-rules.index', ['team' => $team->getRouteKey()]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Team $team, StoreRequest $request, #[CurrentUser] User $user, CreateRecurringRule $createRecurringRule): RedirectResponse
    {
        $rule = $createRecurringRule->for($team)->by($user)->using($request->ruleAttributes());

        $rule->attachTags($request->tagIds());

        Toast::make(trans('Saved'))->flash();

        return to_route('finance.teams.recurring-rules.index', ['team' => $team->getRouteKey()]);
    }

    /**
     * Display the specified resource, with when it fires next and
     * whether it runs, the way the listing reports them.
     */
    public function show(Team $team, RecurringRule $recurringRule): Modal
    {
        return Inertia::modal('finance/teams/recurring-rules/show', [
            'canRecur' => Feature::RecurTransactions->enabled($team),
            'rule' => $recurringRule->load('account', 'currency', 'tags', 'user')->append(['next_due_date', 'status']),
        ])->baseRoute('finance.teams.recurring-rules.index', ['team' => $team->getRouteKey()]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Team $team, RecurringRule $recurringRule): Modal
    {
        return Inertia::modal('finance/teams/recurring-rules/edit', [
            ...$this->form($team),
            'rule' => $recurringRule->load('account', 'currency', 'tags'),
        ])->baseRoute('finance.teams.recurring-rules.index', ['team' => $team->getRouteKey()]);
    }

    /**
     * Update the specified resource in storage. The new template and
     * schedule apply from the next occurrence on; what the rule has
     * already generated stays as it was.
     */
    public function update(Team $team, RecurringRule $recurringRule, UpdateRequest $request, UpdateRecurringRule $updateRecurringRule): RedirectResponse
    {
        $updateRecurringRule->for($team)->using($recurringRule, $request->ruleAttributes(), $request->tagIds());

        Toast::make(trans('Saved'))->flash();

        return back();
    }

    /**
     * Suspend the rule, so it generates nothing until it is resumed.
     */
    public function pause(Team $team, RecurringRule $recurringRule): RedirectResponse
    {
        $recurringRule->deactivate();

        Toast::make(trans('Paused'))->flash();

        return back();
    }

    /**
     * Resume the rule from its next due date. The occurrences of the
     * paused stretch are skipped rather than backfilled, so resuming
     * never floods the ledger.
     */
    public function resume(Team $team, RecurringRule $recurringRule): RedirectResponse
    {
        if (($skipped = $recurringRule->skipped_due_date) instanceof CarbonImmutable) {
            $recurringRule->forceFill(['last_generated_due_date' => $skipped])->save();
        }

        $recurringRule->activate();

        Toast::make(trans('Resumed'))->flash();

        return back();
    }

    /**
     * Remove the specified resource from storage, keeping the
     * transactions it has generated as ordinary entries.
     */
    public function destroy(Team $team, RecurringRule $recurringRule, DeleteRecurringRule $deleteRecurringRule): RedirectResponse
    {
        $deleteRecurringRule->for($team)->using($recurringRule);

        Toast::make(trans('Deleted'))->flash();

        return back();
    }

    /**
     * Take back a repeating entry that was just recorded, removing the
     * rule and the occurrence it generated so a wrong tap leaves
     * nothing behind.
     */
    public function undo(Team $team, RecurringRule $recurringRule, DeleteRecurringRule $deleteRecurringRule): RedirectResponse
    {
        $deleteRecurringRule->for($team)->withGeneratedTransactions()->using($recurringRule);

        Toast::make(trans('Deleted'))->flash();

        return back();
    }

    /**
     * The props the rule form needs: the accounts it may draw on with
     * their active currencies, and the tags it may carry.
     *
     * @return array<string, mixed>
     */
    protected function form(Team $team): array
    {
        return [
            'accounts' => $team->accounts()->isActive()->with(['currencies' => function (Relation $currencies): void {
                $currencies->isActive(); // @phpstan-ignore-line argument.type
            }])->get(),
            'categories' => $this->categoryOptions(),
            'tags' => $team->tags()->isActive()->orderBy('name')->get(),
        ];
    }

    /**
     * Paginate the team's rules by when each one fires next, soonest
     * first, with a rule that has no occurrence left at the end. The
     * next due date is computed from the schedule rather than stored,
     * so the ordering is done in PHP over the team's own rules.
     *
     * @return \Illuminate\Pagination\LengthAwarePaginator<int, \App\Modules\Core\Finance\Models\Team\RecurringRule>
     */
    protected function paginateByNextDueDate(Team $team, Request $request): LengthAwarePaginator
    {
        /** @var \Illuminate\Support\Collection<int, \App\Modules\Core\Finance\Models\Team\RecurringRule> $rules */
        $rules = $team->recurringRules()
            ->with(['account', 'currency', 'tags'])
            ->get()
            ->sortBy(fn (RecurringRule $rule): string => $rule->next_due_date?->toDateString() ?? '9999-12-31')
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();

        /** @var \Illuminate\Support\Collection<int, \App\Modules\Core\Finance\Models\Team\RecurringRule> $items */
        $items = $rules->forPage($page, self::PER_PAGE)->values();

        return new LengthAwarePaginator(
            $this->appendComputed($items),
            $rules->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }

    /**
     * Send the computed next due date and status to the page alongside
     * the stored columns.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Modules\Core\Finance\Models\Team\RecurringRule>  $rules
     * @return \Illuminate\Support\Collection<int, \App\Modules\Core\Finance\Models\Team\RecurringRule>
     */
    protected function appendComputed(Collection $rules): Collection
    {
        return $rules->each(fn (RecurringRule $rule): RecurringRule => $rule->append(['next_due_date', 'status']));
    }
}
