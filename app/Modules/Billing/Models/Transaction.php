<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Attributes\Table;

/**
 * @property int $id
 * @property string $billable_type
 * @property string $billable_id
 * @property \Carbon\CarbonImmutable $billed_at
 * @property string $currency
 * @property string|null $invoice_number
 * @property string $paddle_id
 * @property string|null $paddle_subscription_id
 * @property string $status
 * @property string $tax
 * @property string $total
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $billable
 * @property-read \App\Modules\Billing\Models\Subscription|null $subscription
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Transaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Transaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Transaction query()
 *
 * @mixin \Eloquent
 */
#[Table('billing_transactions')]
class Transaction extends \Laravel\Paddle\Transaction
{
    //
}
