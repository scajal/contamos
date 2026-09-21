<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Models\Team;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetPeriodStatus;
use App\Modules\Support\Concerns\DefinesKeyType;
use App\Modules\Support\Concerns\LogsTeamActivity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One dated stretch of a tag's budget in a single currency:
 * how much the tag is allowed each month, from which month,
 * and until which month if it ever stops.
 *
 * @property string $id
 * @property numeric $amount
 * @property string $currency_id
 * @property \Carbon\CarbonImmutable $from_month
 * @property string $tag_id
 * @property \Carbon\CarbonImmutable|null $until_month
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Modules\Core\Catalog\Models\Currency $currency
 * @property-read \App\Modules\Core\Finance\Models\Team\Tag $tag
 *
 * @method static \Database\Factories\Modules\Core\Finance\Models\Team\BudgetPeriodFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\BudgetPeriod covering(\Carbon\CarbonImmutable $month)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\BudgetPeriod newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\BudgetPeriod newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\BudgetPeriod query()
 *
 * @mixin \Eloquent
 */
#[Fillable('amount', 'currency_id', 'from_month', 'until_month')]
class BudgetPeriod extends Model
{
    /** @use \App\Modules\Support\Concerns\DefinesKeyType<string> */
    use DefinesKeyType;

    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\Modules\Core\Finance\Models\Team\BudgetPeriodFactory> */
    use HasFactory;

    use HasUlids;
    use LogsTeamActivity;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:18',
            // The bounds are whole months, so they are stored
            // as plain dates rather than as timestamps that a
            // string comparison would then sort wrongly.
            'from_month' => 'immutable_date:Y-m-d',
            'until_month' => 'immutable_date:Y-m-d',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // A period is a stretch of whole months, so both
        // bounds are pinned to the first day of theirs
        // however they were handed in.
        static::saving(function (self $period): void {
            $period->from_month = $period->from_month->startOfMonth();
            $period->until_month = $period->until_month?->startOfMonth();
        });
    }

    /**
     * Get the attributes whose changes are recorded in the
     * team's activity log.
     *
     * @return array<int, string>
     */
    public function activityLogAttributes(): array
    {
        return ['amount', $this->currency()->getForeignKeyName(), 'from_month', 'until_month'];
    }

    /**
     * Get the key of the team the period belongs to, which it
     * only reaches through its tag.
     */
    public function activityTeamKey(): ?string
    {
        $tag = $this->relationLoaded('tag') ? $this->tag : $this->tag()->first();

        return $tag?->activityTeamKey();
    }

    /**
     * Get the currency the budget is expressed in.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Catalog\Models\Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Get the tag the budget belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Finance\Models\Team\Tag, $this>
     */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }

    /**
     * Whether the period applies in the given month.
     */
    public function covers(CarbonImmutable $month): bool
    {
        $month = $month->startOfMonth();

        return $this->from_month->lessThanOrEqualTo($month)
            && (! $this->until_month instanceof CarbonImmutable || $this->until_month->greaterThanOrEqualTo($month));
    }

    /**
     * Whether the period overlaps the given months, where a
     * null end means the stretch never closes.
     */
    public function overlaps(CarbonImmutable $from, ?CarbonImmutable $until): bool
    {
        $from = $from->startOfMonth();
        $until = $until?->startOfMonth();

        // The period starts after the other one has closed.
        if ($until instanceof CarbonImmutable && $this->from_month->greaterThan($until)) {
            return false;
        }

        // The period closed before the other one starts.
        return ! $this->until_month instanceof CarbonImmutable || ! $this->until_month->lessThan($from);
    }

    /**
     * Where the period sits relative to the given month. A
     * period whose months are over reads as ended when it
     * was a single-month objective or when another period
     * took over after it, and as paused otherwise.
     */
    public function status(CarbonImmutable $month, bool $isSuperseded = false): BudgetPeriodStatus
    {
        $month = $month->startOfMonth();

        // The period has not started yet.
        if ($this->from_month->greaterThan($month)) {
            return BudgetPeriodStatus::Scheduled;
        }

        // The period applies right now.
        if ($this->covers($month)) {
            return BudgetPeriodStatus::Running;
        }

        // A one-month objective, or a stretch another period
        // replaced, ran its course rather than being stopped.
        $isObjective = $this->until_month instanceof CarbonImmutable
            && $this->from_month->equalTo($this->until_month);

        return $isSuperseded || $isObjective
            ? BudgetPeriodStatus::Ended
            : BudgetPeriodStatus::Paused;
    }

    /**
     * Scope the query to the periods that apply in the
     * given month.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Modules\Core\Finance\Models\Team\BudgetPeriod>  $query
     */
    #[Scope]
    protected function covering(Builder $query, CarbonImmutable $month): void
    {
        $first = $month->startOfMonth()->toDateString();

        // The bounds are compared as dates rather than as raw
        // strings, since a date cast writes a time part that
        // SQLite keeps and a plain comparison then misreads.
        $query->whereDate('from_month', '<=', $first)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('until_month')
                ->orWhereDate('until_month', '>=', $first));
    }
}
