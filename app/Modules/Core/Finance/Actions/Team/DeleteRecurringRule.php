<?php

namespace App\Modules\Core\Finance\Actions\Team;

use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Support\Helpers\TeamActivity;
use Exception;
use Illuminate\Support\Facades\DB;

class DeleteRecurringRule
{
    /**
     * The team that owns the rule to delete.
     */
    public ?Team $team = null;

    /**
     * Whether the transactions the rule already generated go with it.
     */
    public bool $withTransactions = false;

    public function __construct(protected DeleteTransaction $deleteTransaction) {}

    /**
     * Set the team that owns the rule to delete.
     */
    public function for(Team $team): self
    {
        $this->team = $team;
        $this->deleteTransaction->for($team);

        return $this;
    }

    /**
     * Take the transactions the rule has already generated with it,
     * reverting each one's effect on the account balance. This is how
     * undoing a repeating entry leaves nothing behind; deleting a rule
     * from the listing keeps its history instead.
     */
    public function withGeneratedTransactions(): self
    {
        $this->withTransactions = true;

        return $this;
    }

    /**
     * Delete the recurring rule. The transactions it generated stay in
     * the ledger as ordinary entries, losing only the link back to the
     * schedule that produced them.
     */
    public function using(RecurringRule $rule): void
    {
        // If the team is not set, throw an exception.
        if (! $this->team instanceof Team) {
            throw new Exception('A recurring rule cannot be deleted without a team.');
        }

        TeamActivity::within(function () use ($rule): void {
            DB::transaction(function () use ($rule): void {
                if ($this->withTransactions) {
                    foreach ($rule->transactions()->get() as $transaction) {
                        $this->deleteTransaction->using($transaction);
                    }
                }

                // The foreign key nulls itself on delete; the due date
                // belongs to the schedule too, so it goes with it.
                $rule->transactions()->update(['due_date' => null, new Transaction()->recurringRule()->getForeignKeyName() => null]);

                $rule->delete();
            });
        });
    }
}
