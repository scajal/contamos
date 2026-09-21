<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Actions\Team;

use App\Modules\Core\Finance\Actions\Team\Concerns\ReplacesRunningBudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Helpers\TeamActivity;
use Exception;
use Illuminate\Support\Facades\DB;

class UpdateTag
{
    use ReplacesRunningBudgetPeriod;

    /**
     * The team the tag belongs to.
     */
    public ?Team $team = null;

    /**
     * The user changing the tag.
     */
    public ?User $user = null;

    /**
     * Set the user changing the tag.
     */
    public function by(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Set the team the tag belongs to.
     */
    public function for(Team $team): self
    {
        $this->team = $team;

        return $this;
    }

    /**
     * Rename the tag and move its running budget amount to the
     * one given for each currency, leaving past months on the
     * amount that applied to them.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, string|null>  $budgets
     */
    public function using(Tag $tag, array $attributes, array $budgets = []): Tag
    {
        // If the team is not set, throw an exception.
        if (! $this->team instanceof Team) {
            throw new Exception('A tag cannot be updated without a team.');
        }

        // The rename and the budget changes read as one entry
        // in the team's activity log.
        return TeamActivity::within(fn (): Tag => DB::transaction(function () use ($tag, $attributes, $budgets): Tag {
            $tag->update($attributes);

            // Only the currencies the form carried are touched,
            // so a currency left out keeps its budget.
            foreach ($budgets as $currencyId => $amount) {
                $this->replaceRunningPeriod($tag, $currencyId, $amount);
            }

            return $tag;
        }));
    }
}
