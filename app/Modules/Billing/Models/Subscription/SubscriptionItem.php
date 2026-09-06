<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models\Subscription;

use Illuminate\Database\Eloquent\Attributes\Table;

#[Table('billing_subscription_items')]
class SubscriptionItem extends \Laravel\Paddle\SubscriptionItem
{
    //
}
