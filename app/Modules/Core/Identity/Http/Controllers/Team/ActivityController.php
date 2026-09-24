<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Http\Controllers\Team;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Identity\Enums\Team\Activity\ActivitySubject;
use App\Modules\Core\Identity\Http\Requests\Team\Activity\IndexRequest;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\Team\Activity;
use App\Modules\Core\Identity\Reports\Team\Concerns\PresentsActivityEntries;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use InertiaUI\Modal\Modal;
use Spatie\QueryBuilder\QueryBuilder;

class ActivityController
{
    use PresentsActivityEntries;

    /**
     * Display a listing of the resource.
     */
    public function index(Team $team, IndexRequest $request): Response
    {
        // A plan without the activity log sees the page locked. The
        // log is kept for every plan, so none of it travels, or the
        // upgrade would be a blur away.
        if (! Feature::ViewActivity->enabled($team)) {
            return Inertia::render('identity/teams/activity/index', [
                'is_locked' => true,
            ]);
        }

        $members = $this->members($team);

        return Inertia::render('identity/teams/activity/index', [
            'activities' => Inertia::scroll(
                fn (): LengthAwarePaginator => $this->entries($team, $members, QueryBuilder::for($team->activities(), $request)
                    ->allowedFilters(...$request->filters())
                    ->with('causer', 'subject')
                    ->orderBy(Activity::CREATED_AT, 'desc')
                    ->orderBy(new Activity()->getKeyName(), 'desc')
                    ->paginate(10)
                    ->withQueryString()),
            )->defer(),
            'filters' => $request->validated('filter', []),
            'is_locked' => false,
            'members' => $members,
            'subjects' => ActivitySubject::options(),
        ]);
    }

    /**
     * Name the AI assistant an entry was written through, or
     * null when the user acted in the app themselves.
     *
     * @return array{name: string|null}|null
     */
    protected function assistant(Activity $activity): ?array
    {
        $properties = $activity->properties ?? collect();

        if ($properties->get('via') !== 'mcp') {
            return null;
        }

        return ['name' => is_string($name = $properties->get('client_name')) ? $name : null];
    }

    /**
     * Shape the page of entries the timeline renders.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Identity\Models\User>  $members
     * @param  \Illuminate\Pagination\LengthAwarePaginator<int, \App\Modules\Core\Identity\Models\Team\Activity>  $activities
     * @return \Illuminate\Pagination\LengthAwarePaginator<int, array<string, mixed>>
     */
    protected function entries(Team $team, Collection $members, LengthAwarePaginator $activities): LengthAwarePaginator
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Identity\Models\Team\Activity> $rows */
        $rows = $activities->getCollection();

        // The names every reference on the page points at, read
        // once per referenced model rather than once per row.
        $names = $this->references($rows);

        /** @var \Illuminate\Pagination\LengthAwarePaginator<int, array<string, mixed>> $entries */
        $entries = $activities->through(fn (Activity $activity): array => $this->entry($members, $activity, $names));

        return $entries;
    }

    /**
     * Shape one entry, without the values it changed. The timeline
     * only says what happened; the sheet says what it was.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Identity\Models\User>  $members
     * @param  array<string, array<string, string>>  $names
     * @return array<string, mixed>
     */
    protected function entry(Collection $members, Activity $activity, array $names): array
    {
        return [
            'id' => $activity->getKey(),
            'assistant' => $this->assistant($activity),
            'batch_uuid' => $activity->batch_uuid,
            'causer' => $this->causer($members, $activity),
            'created_at' => $activity->created_at?->toIso8601String(),
            'event' => $activity->event,
            'subject_id' => $activity->subject_id,
            'subject_label' => $this->subjectLabel($activity, $names),
            'subject_type' => ActivitySubject::forType($activity->subject_type)?->value,
        ];
    }

    /**
     * Show what a single thing somebody did changed. An entry that
     * belongs to a batch opens with every row the same action
     * wrote, so the sheet says as much as the timeline promised.
     */
    public function show(Team $team, Activity $activity): Modal
    {
        $rows = $this->batch($team, $activity);

        $members = $this->members($team);

        $names = $this->references($rows);

        return Inertia::modal('identity/teams/activity/show', [
            'entries' => $rows
                ->map(fn (Activity $row): array => [
                    ...$this->entry($members, $row, $names),
                    'changes' => $this->changes($row, $names),
                ])
                ->values(),
        ])->baseRoute('identity.teams.activity.index', ['team' => $team->getRouteKey()]);
    }
}
