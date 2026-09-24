<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Events\Team;

use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetAlertKind;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Notifications\Team\BudgetAlertNotification;
use App\Modules\Core\Identity\Events\Team\NotifiesTeam;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Notifications\Team\TeamNotification;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A month reached a running budget's warning threshold or
 * went over its amount.
 */
class BudgetThresholdReached implements NotifiesTeam
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  numeric-string  $amount
     * @param  numeric-string  $spent
     */
    public function __construct(
        public Team $team,
        public BudgetPeriod $budget,
        public BudgetAlertKind $kind,
        public CarbonImmutable $month,
        public string $amount,
        public string $spent,
        public float $percentage,
    ) {}

    /**
     * Get the team the event happened on.
     */
    public function team(): Team
    {
        return $this->team;
    }

    /**
     * Get the user who caused the event, which is nobody: the
     * spending is the team's, so every Owner and Admin
     * hears about it.
     */
    public function causer(): null
    {
        return null;
    }

    /**
     * Get the email the event sends.
     */
    public function notification(): TeamNotification
    {
        return new BudgetAlertNotification($this->team, $this->budget, $this->kind, $this->month, $this->amount, $this->spent, $this->percentage);
    }
}
