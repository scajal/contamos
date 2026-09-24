<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Actions\Team;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Helpers\TeamActivity;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Records a transaction a member enters by hand, on the web or
 * through an assistant: a one-off movement, or one that repeats
 * on a schedule, whose recurring rule is created first and whose
 * first occurrence is recorded only once it is due.
 */
class CreateTransactionEntry
{
    /**
     * The team for which the entry will be recorded.
     */
    public ?Team $team = null;

    /**
     * The user that recorded the entry.
     */
    public ?User $user = null;

    /**
     * Create a new action instance.
     */
    public function __construct(
        private readonly CreateTransaction $createTransaction,
        private readonly CreateRecurringRule $createRecurringRule,
    ) {}

    /**
     * Set the user that recorded the entry.
     */
    public function by(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Set the team for which the entry will be recorded.
     */
    public function for(Team $team): self
    {
        $this->team = $team;

        return $this;
    }

    /**
     * Record the entry from validated attributes: the
     * transaction's own fields (`account_id`, `amount`,
     * `category`, `currency_id`, `description`, `occurred_at` in
     * UTC, `quote`, `type`) and, for a repeating entry, `repeat`
     * with the `recurring_*` schedule fields and `timezone`.
     * Returns the transaction recorded, if any, and the rule a
     * repeating entry creates.
     *
     * @param  array<array-key, mixed>  $attributes
     * @param  array<int, string>  $tagIds
     * @return array{transaction: \App\Modules\Core\Finance\Models\Team\Transaction|null, recurringRule: \App\Modules\Core\Finance\Models\Team\RecurringRule|null}
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException When the team's plan does not include repeating transactions.
     */
    public function using(array $attributes, array $tagIds = []): array
    {
        // If the team is not set, throw an exception.
        if (! $this->team instanceof Team) {
            throw new Exception('A transaction entry cannot be recorded without a team.');
        }

        $team = $this->team;

        $repeats = filter_var($attributes['repeat'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // Repeating an entry is a Pro feature. An entry that still
        // asks for it on another plan is refused rather than
        // silently recorded as a one-off.
        if ($repeats && ! Feature::RecurTransactions->enabled($team)) {
            throw new AuthorizationException(trans('Repeating a transaction requires the Pro plan.'));
        }

        $transactionAttributes = array_intersect_key($attributes, array_flip([
            new Transaction()->account()->getForeignKeyName(),
            'amount',
            'category',
            new Transaction()->currency()->getForeignKeyName(),
            'description',
            'occurred_at',
            'quote',
            'type',
        ]));

        // Everything the entry writes reads as one entry in the
        // team's activity log.
        return TeamActivity::within(fn (): array => DB::transaction(function () use ($team, $repeats, $attributes, $transactionAttributes, $tagIds): array {
            // A repeating entry first creates the rule that will
            // generate its occurrences.
            $rule = $repeats ? $this->createRecurringRule($team, $attributes, $transactionAttributes) : null;

            // A rule due today or earlier generates its first
            // occurrence right away, linked to that due date. A
            // rule starting later waits for its due date.
            $transaction = ! $rule instanceof RecurringRule || $rule->isDueByToday()
                ? $this->recorder($this->createTransaction->for($team))->using([
                    ...$transactionAttributes,
                    ...($rule instanceof RecurringRule
                        ? [$rule->getForeignKey() => $rule->getKey(), 'due_date' => $rule->starts_on]
                        : []),
                ], $tagIds)
                : null;

            // The rule carries the same tags its occurrences will,
            // and once its first occurrence exists the schedule
            // picks up from the one after it.
            if ($rule instanceof RecurringRule) {
                $rule->attachTags($tagIds);

                if ($transaction instanceof Transaction) {
                    $rule->forceFill(['last_generated_due_date' => $rule->starts_on])->save();
                }
            }

            return ['transaction' => $transaction, 'recurringRule' => $rule];
        }));
    }

    /**
     * Create the recurring rule of a repeating entry from the
     * transaction's template fields and the entry's schedule.
     *
     * @param  array<array-key, mixed>  $attributes
     * @param  array<string, mixed>  $transactionAttributes
     */
    protected function createRecurringRule(Team $team, array $attributes, array $transactionAttributes): RecurringRule
    {
        $createRecurringRule = $this->createRecurringRule->for($team);

        if ($this->user instanceof User) {
            $createRecurringRule->by($this->user);
        }

        // The rule is the template of the transaction, without
        // the instant it occurred at or the rate it used.
        return $createRecurringRule->using([
            ...array_diff_key($transactionAttributes, array_flip(['occurred_at', 'quote'])),
            'ends_on' => $attributes['recurring_ends_on'] ?? null,
            'frequency' => $attributes['recurring_frequency'] ?? null,
            'interval' => $attributes['recurring_interval'] ?? null,
            'starts_on' => $attributes['recurring_starts_on'] ?? null,
            'time_of_day' => $attributes['recurring_time_of_day'] ?? null,
            'timezone' => $attributes['timezone'] ?? null,
        ]);
    }

    /**
     * Attribute the transaction to the user recording the entry,
     * when one is set.
     */
    protected function recorder(CreateTransaction $createTransaction): CreateTransaction
    {
        return $this->user instanceof User ? $createTransaction->by($this->user) : $createTransaction;
    }
}
