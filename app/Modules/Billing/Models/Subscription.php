<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Subscription active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Subscription canceled()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Subscription expiredTrial()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Subscription newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Subscription newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Subscription notCanceled()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Subscription notOnGracePeriod()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Subscription notOnPausedGracePeriod()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Subscription notOnTrial()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Subscription notPaused()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Subscription onGracePeriod()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Subscription onPausedGracePeriod()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Subscription onTrial()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Subscription pastDue()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Subscription paused()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Subscription query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Subscription recurring()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Subscription valid()
 *
 * @mixin \Eloquent
 */
#[Table('billing_subscriptions')]
class Subscription extends \Laravel\Paddle\Subscription
{
    //
}
