<?php

namespace App\Modules\Core\Finance\Models\Team;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\RecurringRule\Frequency;
use App\Modules\Core\Finance\Enums\RecurringRule\Status;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Concerns\DefinesKeyType;
use App\Modules\Support\Concerns\HasActivation;
use App\Modules\Support\Concerns\HasTags;
use App\Modules\Support\Concerns\LogsTeamActivity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $account_id
 * @property numeric $amount
 * @property \App\Modules\Core\Finance\Enums\Transaction\Category|null $category
 * @property string $currency_id
 * @property int $decimal_places
 * @property string|null $description
 * @property \Carbon\CarbonImmutable|null $failed_at
 * @property string|null $failure_reason
 * @property bool $is_active
 * @property \Carbon\CarbonImmutable|null $last_generated_due_date
 * @property \App\Modules\Core\Finance\Enums\Transaction\TransactionType $type
 * @property \App\Modules\Core\Finance\Enums\RecurringRule\Frequency $frequency
 * @property int $interval
 * @property \Carbon\CarbonImmutable $starts_on
 * @property \Carbon\CarbonImmutable|null $ends_on
 * @property string $time_of_day
 * @property string $timezone
 * @property string $team_id
 * @property string|null $user_id
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Modules\Core\Finance\Models\Team\Account $account
 * @property-read \App\Modules\Core\Catalog\Models\Currency $currency
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Finance\Models\Team\Tag> $tags
 * @property-read \App\Modules\Core\Identity\Models\Team $team
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Finance\Models\Team\Transaction> $transactions
 * @property-read \App\Modules\Core\Identity\Models\User|null $user
 *
 * @method static \Database\Factories\Modules\Core\Finance\Models\Team\RecurringRuleFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\RecurringRule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\RecurringRule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\RecurringRule query()
 *
 * @mixin \Eloquent
 */
#[Fillable('amount', 'category', 'decimal_places', 'description', 'ends_on', 'failed_at', 'failure_reason', 'frequency', 'interval', 'is_active', 'last_generated_due_date', 'starts_on', 'time_of_day', 'timezone', 'type')]
class RecurringRule extends Model
{
    /** @use \App\Modules\Support\Concerns\DefinesKeyType<string> */
    use DefinesKeyType;

    use HasActivation;

    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\Modules\Core\Finance\Models\Team\RecurringRuleFactory> */
    use HasFactory;

    use HasTags;
    use HasUlids;
    use LogsTeamActivity;

    /**
     * Get the attributes whose changes are recorded in the
     * team's activity log.
     *
     * @return array<int, string>
     */
    public function activityLogAttributes(): array
    {
        return [
            $this->account()->getForeignKeyName(),
            'amount',
            'category',
            $this->currency()->getForeignKeyName(),
            'description',
            'ends_on',
            'frequency',
            'interval',
            'is_active',
            'starts_on',
            'time_of_day',
            'timezone',
            'type',
        ];
    }

    /**
     * Get the key of the team the rule belongs to.
     */
    public function activityTeamKey(): ?string
    {
        return is_string($key = $this->getAttribute($this->team()->getForeignKeyName())) ? $key : null;
    }

    /**
     * Get the account for the rule.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Finance\Models\Team\Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Callback executed when a recurring rule is being created.
        static::creating(function (RecurringRule $rule): void {
            $rule->{$rule->team()->getForeignKeyName()} ??= Account::query()
                ->whereKey($rule->getAttribute($rule->account()->getForeignKeyName()))
                ->value($rule->team()->getForeignKeyName());
        });
    }

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:18',
            'category' => Category::class,
            'decimal_places' => 'integer',
            'ends_on' => 'date',
            'failed_at' => 'datetime',
            'frequency' => Frequency::class,
            'interval' => 'integer',
            'last_generated_due_date' => 'date',
            'starts_on' => 'date',
            'type' => TransactionType::class,
        ];
    }

    /**
     * Get the currency for the rule.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Catalog\Models\Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Determine whether the rule's start date has already been reached
     * today, in the rule's own timezone, so its first occurrence can be
     * generated immediately instead of waiting on a later run.
     */
    public function isDueByToday(): bool
    {
        return ! $this->starts_on->startOfDay()->greaterThan(CarbonImmutable::now($this->timezone)->startOfDay());
    }

    /**
     * Get the instant, in UTC, the rule's start date falls due at,
     * combining the start date with the local time of day in the
     * rule's own timezone.
     */
    public function dueInstant(): CarbonImmutable
    {
        return $this->dueInstantFor($this->starts_on);
    }

    /**
     * Get the instant, in UTC, the given due date falls due at,
     * combining that date with the rule's local time of day in
     * its own timezone.
     */
    public function dueInstantFor(CarbonImmutable $dueDate): CarbonImmutable
    {
        return CarbonImmutable::parse("{$dueDate->toDateString()} {$this->time_of_day}", $this->timezone)->utc();
    }

    /**
     * Get the rule's first due date on or after the given date, or null
     * when the schedule has already ended by then. Due dates are naive
     * calendar days, held as UTC midnight so two of them compare and
     * subtract without a timezone shifting the day.
     */
    public function dueDateOnOrAfter(CarbonImmutable $date): ?CarbonImmutable
    {
        $anchor = $this->startsOnDate();
        $target = $this->toDueDate($date);

        // Nothing happens before the rule starts, so the anchor
        // itself is the first due date on or after any earlier day.
        $due = $target->lessThanOrEqualTo($anchor) ? $anchor : $this->searchDueDate($target);

        return $this->endsOnDate()?->lessThan($due) === true ? null : $due;
    }

    /**
     * Get the rule's first due date strictly after the given date, or
     * null when the schedule has already ended by then.
     */
    public function dueDateAfter(CarbonImmutable $date): ?CarbonImmutable
    {
        return $this->dueDateOnOrAfter($this->toDueDate($date)->addDay());
    }

    /**
     * The rule's next due date: the first occurrence after the last
     * one generated, or its very first when it has generated none. A
     * stretch the rule was not eligible for is skipped by advancing
     * the last generated due date past it, so this never reaches back
     * into one. A schedule that has ended has no next due date.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute<\Carbon\CarbonImmutable|null, never>
     */
    protected function nextDueDate(): Attribute
    {
        return Attribute::get(fn (): ?CarbonImmutable => $this->last_generated_due_date instanceof CarbonImmutable
            ? $this->dueDateAfter($this->last_generated_due_date)
            : $this->dueDateOnOrAfter($this->startsOnDate()))->withoutObjectCaching();
    }

    /**
     * The rule's last due date strictly before today, used to skip a
     * stretch the rule was not eligible for — while it was paused, or
     * while its team was on a plan without recurring transactions —
     * instead of flooding the ledger with it once it is eligible again.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute<\Carbon\CarbonImmutable|null, never>
     */
    protected function skippedDueDate(): Attribute
    {
        return Attribute::get(function (): ?CarbonImmutable {
            $today = $this->today();

            // Walk back from today to the occurrence before it. A rule
            // that has not reached its first due date has nothing to skip.
            $due = $this->dueDateOnOrAfter($today);
            $previous = $due instanceof CarbonImmutable
                ? $this->previousDueDate($due)
                : $this->previousDueDate($this->endsOnDate() ?? $today);

            return $previous?->greaterThan($this->last_generated_due_date ?? $this->startsOnDate()->subDay()) === true
                ? $previous
                : null;
        })->withoutObjectCaching();
    }

    /**
     * Whether the rule may still generate: it is active and its team's
     * plan includes recurring transactions.
     */
    public function isGenerating(): bool
    {
        return $this->is_active && Feature::RecurTransactions->enabled($this->team);
    }

    /**
     * Record why the rule's last run could not generate an occurrence.
     * The due date is left where it was, so the occurrence is retried
     * on the next run once the cause is fixed.
     */
    public function markFailed(string $reason): void
    {
        $this->forceFill([
            'failed_at' => CarbonImmutable::now(),
            'failure_reason' => $reason,
        ])->save();
    }

    /**
     * Clear the rule's failure, after a successful generation or an
     * edit that may have fixed its cause.
     */
    public function clearFailure(): void
    {
        if ($this->failure_reason === null && $this->failed_at === null) {
            return;
        }

        $this->forceFill([
            'failed_at' => null,
            'failure_reason' => null,
        ])->save();
    }

    /**
     * The rule's state for the listing: suspended by a member, failing
     * to generate, or running on schedule.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute<\App\Modules\Core\Finance\Enums\RecurringRule\Status, never>
     */
    protected function status(): Attribute
    {
        return Attribute::get($this->resolveStatus(...))->withoutObjectCaching();
    }

    /**
     * Work out the rule's state. Suspension is the state a member set
     * by hand, so it is what the listing shows even for a rule that
     * also failed to generate.
     */
    public function resolveStatus(): Status
    {
        if (! $this->is_active) {
            return Status::Paused;
        }

        return $this->failure_reason !== null ? Status::Failed : Status::Active;
    }

    /**
     * Get the rule's occurrence the given number of steps after its
     * start date. Monthly and yearly steps are counted from the start
     * date itself, so a rule anchored on the 31st falls on the last day
     * of a shorter month and returns to the 31st when the month allows,
     * and one anchored on 29 February falls on the 28th in common years.
     */
    protected function occurrence(int $steps): CarbonImmutable
    {
        $anchor = $this->startsOnDate();
        $units = $steps * $this->interval;

        return match ($this->frequency) {
            Frequency::Daily => $anchor->addDays($units),
            Frequency::Weekly => $anchor->addWeeks($units),
            Frequency::Monthly => $anchor->addMonthsNoOverflow($units),
            Frequency::Yearly => $anchor->addYearsNoOverflow($units),
        };
    }

    /**
     * Get the first occurrence on or after the given date, which must
     * fall after the rule's start date. The step count is estimated
     * from the elapsed units and then corrected, so a clamped month
     * never lands on the wrong side of the target.
     */
    protected function searchDueDate(CarbonImmutable $target): CarbonImmutable
    {
        $anchor = $this->startsOnDate();

        $elapsed = match ($this->frequency) {
            Frequency::Daily => $anchor->diffInDays($target),
            Frequency::Weekly => $anchor->diffInWeeks($target),
            Frequency::Monthly => $anchor->diffInMonths($target),
            Frequency::Yearly => $anchor->diffInYears($target),
        };

        $steps = max(0, (int) floor($elapsed / $this->interval));
        $due = $this->occurrence($steps);

        while ($due->lessThan($target)) {
            $due = $this->occurrence(++$steps);
        }

        // Walk back when the elapsed-unit estimate overshoots,
        // which a clamped month can do relative to the target.
        while ($steps > 0 && ($earlier = $this->occurrence($steps - 1))->greaterThanOrEqualTo($target)) { // @codeCoverageIgnore
            $steps--; // @codeCoverageIgnore
            $due = $earlier; // @codeCoverageIgnore
        }

        return $due;
    }

    /**
     * Get the occurrence immediately before the given due date, or null
     * when that date is the rule's first.
     */
    protected function previousDueDate(CarbonImmutable $dueDate): ?CarbonImmutable
    {
        $anchor = $this->startsOnDate();
        $date = $this->toDueDate($dueDate);

        if ($date->lessThanOrEqualTo($anchor)) {
            return null;
        }

        // The occurrence on or after the day before is either the day
        // itself, whose predecessor is one step back, or already that
        // predecessor.
        $due = $this->searchDueDate($date->subDay());

        return $due->greaterThanOrEqualTo($date) ? $this->stepBack($due) : $due;
    }

    /**
     * Get the occurrence one step before the given one.
     */
    protected function stepBack(CarbonImmutable $dueDate): ?CarbonImmutable
    {
        $steps = 0;

        while ($this->occurrence($steps)->lessThan($dueDate)) {
            $steps++;
        }

        return $steps > 0 ? $this->occurrence($steps - 1) : null;
    }

    /**
     * Today's calendar day in the rule's own timezone, held as UTC
     * midnight like every other due date.
     */
    protected function today(): CarbonImmutable
    {
        return CarbonImmutable::parse(CarbonImmutable::now($this->timezone)->toDateString());
    }

    /**
     * The rule's start date as a bare calendar day.
     */
    protected function startsOnDate(): CarbonImmutable
    {
        return $this->toDueDate($this->starts_on);
    }

    /**
     * The rule's inclusive end date as a bare calendar day, when it has one.
     */
    protected function endsOnDate(): ?CarbonImmutable
    {
        return $this->ends_on instanceof CarbonImmutable ? $this->toDueDate($this->ends_on) : null;
    }

    /**
     * Reduce an instant to the calendar day it falls on, held as UTC
     * midnight so due dates compare as days rather than moments.
     */
    protected function toDueDate(CarbonImmutable $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date->toDateString());
    }

    /**
     * Get the team for the rule.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Identity\Models\Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the transactions generated from the rule.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Modules\Core\Finance\Models\Team\Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the user who created the rule.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Identity\Models\User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
