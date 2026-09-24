<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Actions\Team;

use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Support\Helpers\TeamActivity;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Support\Facades\DB;

class ResumeRecurringRule
{
    /**
     * The team that owns the rule to resume.
     */
    public ?Team $team = null;

    /**
     * Set the team that owns the rule to resume.
     */
    public function for(Team $team): self
    {
        $this->team = $team;

        return $this;
    }

    /**
     * Resume the rule from its next due date. The occurrences of
     * the paused stretch are skipped rather than backfilled, so
     * resuming never floods the ledger.
     */
    public function using(RecurringRule $rule): RecurringRule
    {
        // If the team is not set, throw an exception.
        if (! $this->team instanceof Team) {
            throw new Exception('A recurring rule cannot be resumed without a team.');
        }

        return TeamActivity::within(fn (): RecurringRule => DB::transaction(function () use ($rule): RecurringRule {
            // Mark the paused stretch as done, so the rule picks
            // up at its next due date after today.
            if (($skipped = $rule->skipped_due_date) instanceof CarbonImmutable) {
                $rule->forceFill(['last_generated_due_date' => $skipped])->save();
            }

            // Turn the rule back on.
            $rule->activate();

            return $rule;
        }));
    }
}
