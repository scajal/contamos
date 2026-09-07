<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models\Subscription;

use Illuminate\Database\Eloquent\Attributes\Table;

/**
 * @property int $id
 * @property string $price_id
 * @property string $product_id
 * @property int $quantity
 * @property string $status
 * @property int $subscription_id
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Modules\Billing\Models\Subscription $subscription
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Subscription\SubscriptionItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Subscription\SubscriptionItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Subscription\SubscriptionItem query()
 *
 * @mixin \Eloquent
 */
#[Table('billing_subscription_items')]
class SubscriptionItem extends \Laravel\Paddle\SubscriptionItem
{
    //
}
