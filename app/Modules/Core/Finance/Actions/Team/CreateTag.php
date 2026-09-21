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

class CreateTag
{
    use ReplacesRunningBudgetPeriod;

    /**
     * The team the tag will be created for.
     */
    public ?Team $team = null;

    /**
     * The user creating the tag.
     */
    public ?User $user = null;

    /**
     * Set the user creating the tag.
     */
    public function by(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Set the team the tag will be created for.
     */
    public function for(Team $team): self
    {
        $this->team = $team;

        return $this;
    }

    /**
     * Create the tag and open a budget period from the current
     * month for every currency an amount was given for.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, string|null>  $budgets
     */
    public function using(array $attributes, array $budgets = []): Tag
    {
        // If the team is not set, throw an exception.
        if (! $this->team instanceof Team) {
            throw new Exception('A tag cannot be created without a team.');
        }

        $team = $this->team;

        // The tag and the budgets it starts with read as one
        // entry in the team's activity log.
        return TeamActivity::within(fn (): Tag => DB::transaction(function () use ($team, $attributes, $budgets): Tag {
            /** @var \App\Modules\Core\Finance\Models\Team\Tag $tag */
            $tag = $team->tags()->create($attributes);

            // A blank amount on a brand new tag leaves the
            // currency without a budget.
            foreach ($budgets as $currencyId => $amount) {
                $this->replaceRunningPeriod($tag, $currencyId, $amount);
            }

            return $tag;
        }));
    }
}
