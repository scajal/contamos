<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Concerns;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Identity\Enums\Team\Activity\ActivitySubject;
use App\Modules\Core\Identity\Enums\Team\Permission;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\Team\Activity;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Core\Identity\Reports\Team\Concerns\PresentsActivityEntries;
use Illuminate\Database\Eloquent\Collection;

/**
 * Presents a team's activity entries to an assistant the way
 * the activity pages show them, behind the same permission
 * and plan gate, and says which entries an assistant wrote.
 *
 * @mixin \Laravel\Mcp\Server\Tool
 */
trait PresentsTeamActivity
{
    use InteractsWithTeams;
    use PresentsActivityEntries;
    use PresentsRecords;

    /**
     * Refuse the call unless the user may open the activity
     * log and the team's plan includes it. The permission is
     * checked first, as on the web, so a member is never
     * offered an upgrade they may not ask for.
     */
    protected function ensureActivityAccess(User $user, Team $team): void
    {
        $this->ensurePermission($user, $team, Permission::ViewActivity);

        $this->ensureFeature($team, Feature::ViewActivity);
    }

    /**
     * Present one entry: what happened, to what, by whom, and
     * through which assistant when it was written over MCP.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Identity\Models\User>  $members
     * @param  array<string, array<string, string>>  $names
     * @return array<string, mixed>
     */
    protected function activityEntry(Collection $members, Activity $activity, array $names, ?string $timezone): array
    {
        $via = $activity->properties?->get('via');
        $clientId = $activity->properties?->get('client_id');
        $clientName = $activity->properties?->get('client_name');

        return [
            'id' => $activity->getKey(),
            'batch_uuid' => $activity->batch_uuid,
            'event' => $activity->event,
            'subject_type' => ActivitySubject::forType($activity->subject_type)?->value,
            'subject_id' => $activity->subject_id,
            'subject_label' => $this->subjectLabel($activity, $names),
            'causer' => $this->causer($members, $activity),
            ...$this->instant('created_at', $activity->created_at?->toImmutable(), $timezone),
            'via' => is_string($via) ? $via : null,
            'client_id' => is_string($clientId) ? $clientId : null,
            'client_name' => is_string($clientName) ? $clientName : null,
        ];
    }
}
