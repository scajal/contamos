<?php

declare(strict_types=1);

namespace App\Modules\Support\Concerns;

use App\Modules\Core\Identity\Models\Team\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Records every change to the model in the activity log of the
 * team it belongs to, so a new write path cannot silently skip
 * the log.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait LogsTeamActivity
{
    use LogsActivity;

    /**
     * Get the attributes whose changes are worth recording.
     * Timestamps, keys, balances and internal columns never
     * belong here.
     *
     * @return array<int, string>
     */
    abstract public function activityLogAttributes(): array;

    /**
     * Get the key of the team the model's entries belong to.
     */
    abstract public function activityTeamKey(): ?string;

    /**
     * Write the team the entry belongs to before the package
     * saves it. The package calls this on the subject of
     * every entry it is about to log.
     */
    public function beforeActivityLogged(Activity $activity, string $event): void
    {
        $activity->setAttribute($activity->team()->getForeignKeyName(), $this->activityTeamKey());
    }

    /**
     * Get the options the package records the model with.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->activityLogAttributes())
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
