<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Events\Team;

use App\Modules\Core\Finance\Enums\RecurringRule\FailureReason;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Core\Finance\Notifications\Team\RecurringRuleFailedNotification;
use App\Modules\Core\Identity\Events\Team\NotifiesTeam;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Core\Identity\Notifications\Team\TeamNotification;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A recurring rule that was generating on schedule could not
 * generate its occurrence.
 */
class RecurringRuleFailed implements NotifiesTeam
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public RecurringRule $rule,
        public FailureReason $reason,
    ) {}

    /**
     * Get the team the event happened on.
     */
    public function team(): Team
    {
        return $this->rule->team;
    }

    /**
     * Get the user who caused the event, which is nobody,
     * since the scheduled run failed on its own.
     */
    public function causer(): ?User
    {
        return null;
    }

    /**
     * Get the email the event sends.
     */
    public function notification(): TeamNotification
    {
        return new RecurringRuleFailedNotification($this->rule->team, $this->rule, $this->reason);
    }
}
