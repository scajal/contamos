<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Models\Team;

use App\Modules\Core\Identity\Enums\Team\Activity\ActivitySubject;
use App\Modules\Core\Identity\Models\Team;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Activity as BaseActivity;

/**
 * An entry of a team's activity log. The package owns the table
 * and the writing side; this model adds the team the entry
 * belongs to and the scopes the timeline filters by.
 *
 * @property string|null $batch_uuid
 * @property string|null $team_id
 * @property-read \App\Modules\Core\Identity\Models\Team|null $team
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Identity\Models\Team\Activity byCauser(string $causer)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Identity\Models\Team\Activity forSubjectType(string $subject)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Identity\Models\Team\Activity newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Identity\Models\Team\Activity newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Identity\Models\Team\Activity occurredFrom(string $date)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Identity\Models\Team\Activity occurredUntil(string $date)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Identity\Models\Team\Activity query()
 *
 * @mixin \Eloquent
 */
class Activity extends BaseActivity
{
    /**
     * Scope the query to the entries a given user caused.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Modules\Core\Identity\Models\Team\Activity>  $query
     */
    #[Scope]
    protected function byCauser(Builder $query, string $causer): void
    {
        $query->where('causer_id', $causer);
    }

    /**
     * Scope the query to the entries about a kind of record, given
     * the alias the timeline filters by. An alias the timeline does
     * not know leaves the query untouched.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Modules\Core\Identity\Models\Team\Activity>  $query
     */
    #[Scope]
    protected function forSubjectType(Builder $query, string $subject): void
    {
        $query->when(
            ActivitySubject::tryFrom($subject),
            fn (Builder $query, ActivitySubject $subject): Builder => $query->where('subject_type', $subject->modelClass()),
        );
    }

    /**
     * Scope the query to the entries recorded on or after
     * the given date.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Modules\Core\Identity\Models\Team\Activity>  $query
     */
    #[Scope]
    protected function occurredFrom(Builder $query, string $date): void
    {
        $query->whereDate(self::CREATED_AT, '>=', $date);
    }

    /**
     * Scope the query to the entries recorded on or before
     * the given date.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Modules\Core\Identity\Models\Team\Activity>  $query
     */
    #[Scope]
    protected function occurredUntil(Builder $query, string $date): void
    {
        $query->whereDate(self::CREATED_AT, '<=', $date);
    }

    /**
     * Get the team the entry belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Identity\Models\Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
