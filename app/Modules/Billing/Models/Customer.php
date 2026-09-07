<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Attributes\Table;

/**
 * @property int $id
 * @property string $billable_type
 * @property string $billable_id
 * @property string $email
 * @property string $name
 * @property string $paddle_id
 * @property \Carbon\CarbonImmutable|null $trial_ends_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $billable
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Customer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Customer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Billing\Models\Customer query()
 *
 * @mixin \Eloquent
 */
#[Table('billing_customers')]
class Customer extends \Laravel\Paddle\Customer
{
    //
}
