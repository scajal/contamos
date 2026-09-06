<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Attributes\Table;

#[Table('billing_transactions')]
class Transaction extends \Laravel\Paddle\Transaction
{
    //
}
