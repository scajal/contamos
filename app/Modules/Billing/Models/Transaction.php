<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Attributes\Table;

/**
 * @property int $id
 * @property string $billable_type
 * @property string $billable_id
 * @property string $paddle_id
 * @property string|null $paddle_subscription_id
 * @property string|null $invoice_number
 * @property string $status
 * @property string $total
 * @property string $tax
 * @property string $currency
 * @property \Carbon\CarbonImmutable $billed_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Modules\Core\Identity\Models\Team $billable
 */
#[Table('billing_transactions')]
class Transaction extends \Laravel\Paddle\Transaction
{
    //
}
