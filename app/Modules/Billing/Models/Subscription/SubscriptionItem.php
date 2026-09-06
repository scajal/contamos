<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models\Subscription;

use Illuminate\Database\Eloquent\Attributes\Table;

/**
 * @property int $id
 * @property string $product_id
 * @property string $price_id
 * @property int $quantity
 * @property string $status
 * @property int $subscription_id
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Modules\Billing\Models\Subscription|null $subscription
 */
#[Table('billing_subscription_items')]
class SubscriptionItem extends \Laravel\Paddle\SubscriptionItem
{
    //
}
