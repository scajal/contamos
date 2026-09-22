<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Administration\Resources\Subscriptions\Pages;

use App\Modules\Administration\Panels\Administration\Resources\Subscriptions\SubscriptionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewSubscription extends ViewRecord
{
    protected static string $resource = SubscriptionResource::class;
}
