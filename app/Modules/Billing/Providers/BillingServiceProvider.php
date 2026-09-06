<?php

declare(strict_types=1);

namespace App\Modules\Billing\Providers;

use App\Modules\Billing\Models\Customer;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\Subscription\SubscriptionItem;
use App\Modules\Billing\Models\Transaction;
use Illuminate\Support\ServiceProvider;
use Laravel\Paddle\Cashier;

class BillingServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->configureModels();
    }

    /**
     * Configure the models that the billing module
     * uses.
     */
    protected function configureModels(): void
    {
        Cashier::useCustomerModel(Customer::class);
        Cashier::useSubscriptionModel(Subscription::class);
        Cashier::useSubscriptionItemModel(SubscriptionItem::class);
        Cashier::useTransactionModel(Transaction::class);
    }
}
