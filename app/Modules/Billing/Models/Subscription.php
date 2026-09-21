<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\Team\Activity;
use App\Modules\Support\Concerns\LogsTeamActivity;
use Illuminate\Database\Eloquent\Attributes\Table;

/**
 * @property int $id
 * @property string $billable_type
 * @property string $billable_id
 * @property \Carbon\CarbonImmutable|null $ends_at
 * @property string $paddle_id
 * @property \Carbon\CarbonImmutable|null $paused_at
 * @property string $status
 * @property \Carbon\CarbonImmutable|null $trial_ends_at
 * @property string $type
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $billable
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Billing\Models\Subscription\SubscriptionItem> $items
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Billing\Models\Transaction> $transactions
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Subscription expiredTrial()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Subscription newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Subscription newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Subscription notCanceled()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Subscription notOnGracePeriod()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Subscription notOnPausedGracePeriod()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Subscription notOnTrial()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Subscription notPaused()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Subscription query()
 *
 * @mixin \Eloquent
 */
#[Table('billing_subscriptions')]
class Subscription extends \Laravel\Paddle\Subscription
{
    use LogsTeamActivity;

    /**
     * Write the team the entry belongs to and leave it with no
     * causer: Paddle drives every status change, so nobody in
     * the team performed it.
     */
    public function beforeActivityLogged(Activity $activity, string $event): void
    {
        $activity->setAttribute($activity->team()->getForeignKeyName(), $this->activityTeamKey());

        $activity->causer()->dissociate();
    }

    /**
     * Get the attributes whose changes are recorded in the
     * team's activity log.
     *
     * @return array<int, string>
     */
    public function activityLogAttributes(): array
    {
        return ['ends_at', 'paused_at', 'status', 'trial_ends_at'];
    }

    /**
     * Get the key of the team the subscription belongs to.
     * Paddle bills teams, so anything else is billed for
     * nobody's log.
     */
    public function activityTeamKey(): ?string
    {
        if ($this->billable_type !== Team::class) {
            return null;
        }

        return $this->billable_id;
    }
}
