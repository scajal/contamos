<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Actions\Team;

use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Support\Helpers\TeamActivity;
use Exception;
use Illuminate\Support\Facades\DB;

class DeleteBudgetPeriod
{
    /**
     * The team the period belongs to.
     */
    public ?Team $team = null;

    /**
     * Set the team the period belongs to.
     */
    public function for(Team $team): self
    {
        $this->team = $team;

        return $this;
    }

    /**
     * Remove the period outright, leaving the months it
     * covered without a budget.
     */
    public function using(BudgetPeriod $period): void
    {
        // If the team is not set, throw an exception.
        if (! $this->team instanceof Team) {
            throw new Exception('A budget period cannot be deleted without a team.');
        }

        // Everything the removal writes reads as one entry in
        // the team's activity log.
        TeamActivity::within(fn (): mixed => DB::transaction(fn (): mixed => $period->delete()));
    }
}
