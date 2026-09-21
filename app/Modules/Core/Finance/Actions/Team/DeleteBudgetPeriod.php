<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Actions\Team;

use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Support\Helpers\TeamActivity;
use Exception;
use Illuminate\Support\Facades\DB;

class DeleteBudgetPeriod
{
    /**
     * The tag the period belongs to.
     */
    public ?Tag $tag = null;

    /**
     * Set the tag the period belongs to.
     */
    public function for(Tag $tag): self
    {
        $this->tag = $tag;

        return $this;
    }

    /**
     * Remove the period outright, leaving the months it
     * covered without a budget.
     */
    public function using(BudgetPeriod $period): void
    {
        // If the tag is not set, throw an exception.
        if (! $this->tag instanceof Tag) {
            throw new Exception('A budget period cannot be deleted without a tag.');
        }

        // Everything the removal writes reads as one entry in
        // the team's activity log.
        TeamActivity::within(fn (): mixed => DB::transaction(function () use ($period): mixed {
            // The period reaches its team through the tag, so the
            // relation is kept loaded for the log entry.
            $period->setRelation('tag', $this->tag);

            return $period->delete();
        }));
    }
}
