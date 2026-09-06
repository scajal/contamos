<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Attributes\Table;

#[Table('billing_customers')]
class Customer extends \Laravel\Paddle\Customer
{
    //
}
