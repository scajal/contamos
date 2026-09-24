<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Actions\Team;

use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Core\Identity\Models\Team;
use Exception;

class PauseRecurringRule
{
    /**
     * The team that owns the rule to pause.
     */
    public ?Team $team = null;

    /**
     * Set the team that owns the rule to pause.
     */
    public function for(Team $team): self
    {
        $this->team = $team;

        return $this;
    }

    /**
     * Suspend the rule, so it generates nothing until it is
     * resumed. A failure it recorded stays, so resuming shows
     * it again.
     */
    public function using(RecurringRule $rule): RecurringRule
    {
        // If the team is not set, throw an exception.
        if (! $this->team instanceof Team) {
            throw new Exception('A recurring rule cannot be paused without a team.');
        }

        // Turn the rule off.
        $rule->deactivate();

        return $rule;
    }
}
