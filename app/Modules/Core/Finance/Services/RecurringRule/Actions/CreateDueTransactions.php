<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Services\RecurringRule\Actions;

use App\Modules\Core\Finance\Actions\Team\CreateTransaction;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Helpers\Instant;
use Carbon\CarbonImmutable;

class CreateDueTransactions
{
    public function __construct(protected CreateTransaction $createTransaction) {}

    /**
     * Generate every occurrence the rule owes up to the given instant,
     * one transaction per due date, each stamped with the instant that
     * due date falls on so downtime never shifts the ledger. The rule
     * is written through the ordinary create action, so balances,
     * tags, attribution, and the activity log match a manual entry.
     *
     * An occurrence that cannot be written — no rate for its instant,
     * an inactive account, a currency no longer on the account — stops
     * the run for that rule and records why, leaving the due date
     * where it is so the next run retries it once the cause is fixed.
     */
    public function using(RecurringRule $rule, CarbonImmutable $until): int
    {
        $created = 0;

        while (($dueDate = $rule->next_due_date) instanceof CarbonImmutable) {
            $dueInstant = $rule->dueInstantFor($dueDate);

            // Nothing is generated before its due instant has passed.
            if ($dueInstant->greaterThan($until)) {
                break;
            }

            if (($reason = $this->reasonItCannotGenerate($rule, $dueInstant)) !== null) {
                $rule->markFailed($reason);

                return $created;
            }

            $this->generate($rule, $dueDate, $dueInstant);

            $rule->forceFill(['last_generated_due_date' => $dueDate])->save();

            $created++;
        }

        if ($created > 0) {
            $rule->clearFailure();
        }

        return $created;
    }

    /**
     * Write the occurrence for the given due date.
     */
    protected function generate(RecurringRule $rule, CarbonImmutable $dueDate, CarbonImmutable $dueInstant): void
    {
        // The rule's creator is the actor, so a generated row reads in
        // the activity log the way a manual one does. A rule outlives
        // the member who made it, and then it has no actor.
        $createTransaction = $this->createTransaction->for($rule->team);

        if ($rule->user instanceof User) {
            $createTransaction = $createTransaction->by($rule->user);
        }

        $createTransaction
            ->using([
                $rule->account()->getForeignKeyName() => $rule->getAttribute($rule->account()->getForeignKeyName()),
                'amount' => $rule->amount,
                'category' => $rule->category,
                $rule->currency()->getForeignKeyName() => $rule->getAttribute($rule->currency()->getForeignKeyName()),
                'description' => $rule->description,
                'due_date' => $dueDate,
                'occurred_at' => $dueInstant,
                'quote' => Instant::resolveQuote($rule->currency, $dueInstant->toIso8601String())?->rate,
                new Transaction()->recurringRule()->getForeignKeyName() => $rule->getKey(),
                'type' => $rule->type,
            ], $rule->tags->modelKeys());
    }

    /**
     * Why the rule cannot generate its occurrence at the given instant,
     * or null when it can.
     */
    protected function reasonItCannotGenerate(RecurringRule $rule, CarbonImmutable $dueInstant): ?string
    {
        if (! $rule->account->is_active) {
            return trans('The account is inactive.');
        }

        if (! $rule->account->currencies()->whereKey($rule->getAttribute($rule->currency()->getForeignKeyName()))->exists()) {
            return trans('The currency is no longer on the account.');
        }

        if (! Instant::resolveQuote($rule->currency, $dueInstant->toIso8601String()) instanceof \App\Modules\Core\Catalog\Models\Currency\Quote) {
            return trans('There is no rate for the due date.');
        }

        return null;
    }
}
