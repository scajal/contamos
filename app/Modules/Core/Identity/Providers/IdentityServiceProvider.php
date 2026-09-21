<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Providers;

use App\Modules\Support\Helpers\TeamActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Actions\LogActivityAction;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;
use Spatie\Activitylog\Support\PendingActivityLog;

class IdentityServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->configureActivityLog();
    }

    /**
     * Configure how the team activity log is written.
     */
    protected function configureActivityLog(): void
    {
        // A fresh application registers the callback once, so
        // repeated boots never stack it.
        LogActivityAction::clearBeforeLoggingCallbacks();

        // Stamp every entry with the batch open while it was
        // written, so one thing somebody did reads as one entry
        // however many rows it touched.
        PendingActivityLog::beforeLogging(function (ActivityContract $activity): void {
            if ($activity instanceof Model) {
                $activity->setAttribute('batch_uuid', TeamActivity::uuid());
            }
        });
    }
}
