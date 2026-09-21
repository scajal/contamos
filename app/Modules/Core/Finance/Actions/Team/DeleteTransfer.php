<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Actions\Team;

use App\Modules\Core\Finance\Actions\Team\Concerns\AppliesTransactionBalances;
use App\Modules\Core\Finance\Models\Team\Transfer;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Support\Helpers\TeamActivity;
use Exception;
use Illuminate\Support\Facades\DB;

class DeleteTransfer
{
    use AppliesTransactionBalances;

    /**
     * The team that owns the transfer to delete.
     */
    public ?Team $team = null;

    /**
     * Set the team that owns the transfer to delete.
     */
    public function for(Team $team): self
    {
        $this->team = $team;

        return $this;
    }

    /**
     * Delete the transfer along with the transactions it owns, and
     * revert their effect on the account balances.
     */
    public function using(Transfer $transfer): void
    {
        // If the team is not set, throw an exception.
        if (! $this->team instanceof Team) {
            throw new Exception('A transfer cannot be deleted without a team.');
        }

        $team = $this->team;

        // Everything the action writes reads as one entry in
        // the team's activity log.
        TeamActivity::within(function () use ($team, $transfer): void {
            DB::transaction(function () use ($team, $transfer): void {
                // Load the transactions the transfer owns.
                $transfer->load('transactions');

                foreach ($transfer->transactions as $transaction) {
                    // Revert its effect on the account balance.
                    $this->revertBalance($team, $transaction);

                    // Delete the owned transaction.
                    $transaction->delete();
                }

                // Delete the transfer.
                $transfer->delete();
            });
        });
    }
}
