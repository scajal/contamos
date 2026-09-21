<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Services\RecurringRule\Commands;

use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Core\Finance\Services\RecurringRule\Actions\CreateDueTransactions;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Pennant\Feature as Pennant;

#[Signature('finance:generate-recurring-transactions')]
#[Description('Generate the transactions every due recurring rule owes')]
class GenerateRecurringTransactionsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CreateDueTransactions $action): int
    {
        // Plan state is resolved fresh for this run, so a team that
        // upgraded since the last one is not held to what its plan
        // answered then.
        Pennant::flushCache();

        $now = CarbonImmutable::now();
        $created = 0;

        RecurringRule::query()
            ->with(['account.currencies', 'currency.quotes', 'tags', 'team', 'user'])
            ->each(function (RecurringRule $rule) use ($action, $now, &$created): void {
                // A paused rule, and a rule whose team is on a plan
                // without recurring transactions, generate nothing.
                // The stretch they were not eligible for is skipped
                // rather than backfilled when they become eligible
                // again, so resuming or upgrading never floods a
                // ledger with months of entries at once.
                if (! $rule->isGenerating()) {
                    $this->skip($rule);

                    return;
                }

                $created += $action->using($rule, $now);
            });

        $this->info("Created {$created} recurring transactions.");

        return self::SUCCESS;
    }

    /**
     * Move a rule that may not generate past the occurrences it is
     * missing, so they are never written after the fact.
     */
    protected function skip(RecurringRule $rule): void
    {
        if (($skipped = $rule->skipped_due_date) instanceof CarbonImmutable) {
            $rule->forceFill(['last_generated_due_date' => $skipped])->save();
        }
    }
}
