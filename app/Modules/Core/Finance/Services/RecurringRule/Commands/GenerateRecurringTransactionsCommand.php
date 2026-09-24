<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Services\RecurringRule\Commands;

use App\Modules\Core\Finance\Actions\Team\EvaluateBudgetAlerts;
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
    public function handle(CreateDueTransactions $action, EvaluateBudgetAlerts $evaluateBudgetAlerts): int
    {
        // Plan state is resolved fresh for this run, so a team that
        // upgraded since the last one is not held to what its plan
        // answered then.
        Pennant::flushCache();

        $now = CarbonImmutable::now();
        $created = 0;

        /** @var array<string, array{team: \App\Modules\Core\Identity\Models\Team, occurred_at: array<int, \Carbon\CarbonImmutable>}> $generated */
        $generated = [];

        RecurringRule::query()
            ->with(['account.currencies', 'currency.quotes', 'tags', 'team', 'user'])
            ->each(function (RecurringRule $rule) use ($action, $now, &$created, &$generated): void {
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

                $count = $action->using($rule, $now);
                $created += $count;

                // Remember the team and the latest occurrence the
                // rule wrote, so its budgets are read once the run
                // is done.
                if ($count > 0 && $rule->last_generated_due_date instanceof CarbonImmutable) {
                    $generated[$rule->team->getKey()]['team'] = $rule->team;
                    $generated[$rule->team->getKey()]['occurred_at'][] = $rule->dueInstantFor($rule->last_generated_due_date);
                }
            });

        // Alert once per team about the budgets the generated
        // movements pushed past a threshold.
        foreach ($generated as $entry) {
            $evaluateBudgetAlerts->for($entry['team'])
                ->using($entry['occurred_at']);
        }

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
