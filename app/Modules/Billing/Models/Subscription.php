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
 * @property-read \App\Modules\Core\Identity\Models\Team $billable
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Billing\Models\Subscription\SubscriptionItem> $items
 */
#[Table('billing_subscriptions')]
class Subscription extends \Laravel\Paddle\Subscription
{
    //
}
