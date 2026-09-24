<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Providers;

use App\Modules\Core\Identity\Events\Team\NotifiesTeam;
use App\Modules\Core\Identity\Listeners\Team\SendTeamNotification;
use App\Modules\Core\Identity\Models\Team\Activity;
use App\Modules\Mcp\Helpers\McpContext;
use App\Modules\Support\Helpers\TeamActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
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
        $this->configureTeamNotifications();
    }

    /**
     * Send the Owner and Admins of a team the email of every
     * event that notifies the team. Listening on the contract
     * wires every such event, present and future, at once.
     */
    protected function configureTeamNotifications(): void
    {
        Event::listen(NotifiesTeam::class, SendTeamNotification::class);
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

            // An entry written while an AI assistant works through
            // the MCP server says so, and names the assistant. The
            // causer stays the user it acted as.
            if ($activity instanceof Activity && ($properties = McpContext::activityProperties()) !== []) {
                $activity->properties = collect($activity->properties ?? [])->merge($properties);
            }
        });
    }
}
