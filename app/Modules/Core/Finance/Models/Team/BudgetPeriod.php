<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Models\Team;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetPeriodStatus;
use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetTargetKind;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Identity\Models\Team;
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
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One dated stretch of a budget in a single currency: how much
 * the target it follows is allowed each month, from which
 * month, and until which month if it ever stops. The
 * target is a category or a tag, never both. A tag period
 * may sit under a category period as one of its sub-budgets,
 * in which case it only counts the tag's movements filed
 * under that category. A period may also name the share of
 * the month's pot at which the team wants to be warned.
 *
 * @property string $id
 * @property numeric $amount
 * @property \App\Modules\Core\Finance\Enums\Transaction\Category|null $category
 * @property string $currency_id
 * @property \Carbon\CarbonImmutable $from_month
 * @property string|null $parent_id
 * @property string $team_id
 * @property string|null $tag_id
 * @property \Carbon\CarbonImmutable|null $until_month
 * @property int|null $warning_threshold
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Finance\Models\Team\BudgetAlert> $alerts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Finance\Models\Team\BudgetPeriod> $children
 * @property-read \App\Modules\Core\Catalog\Models\Currency $currency
 * @property-read \App\Modules\Core\Finance\Models\Team\BudgetPeriod|null $parent
 * @property-read \App\Modules\Core\Finance\Models\Team\Tag|null $tag
 * @property-read \App\Modules\Core\Identity\Models\Team $team
 *
 * @method static \Database\Factories\Modules\Core\Finance\Models\Team\BudgetPeriodFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\BudgetPeriod covering(\Carbon\CarbonImmutable $month)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\BudgetPeriod newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\BudgetPeriod newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\BudgetPeriod query()
 *
 * @mixin \Eloquent
 */
#[Fillable('amount', 'category', 'currency_id', 'from_month', 'until_month', 'warning_threshold')]
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
            'category' => Category::class,
            // The bounds are whole months, so they are stored
            // as plain dates rather than as timestamps that a
            // string comparison would then sort wrongly.
            'from_month' => 'immutable_date:Y-m-d',
            'until_month' => 'immutable_date:Y-m-d',
            'warning_threshold' => 'integer',
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
        return ['amount', $this->currency()->getForeignKeyName(), 'from_month', 'until_month', 'warning_threshold'];
    }

    /**
     * Get the key of the team the period belongs to, which a
     * category budget has no tag to reach through.
     */
    public function activityTeamKey(): ?string
    {
        return is_string($key = $this->getAttribute($this->team()->getForeignKeyName())) ? $key : null;
    }

    /**
     * Get the alerts the period sent, one per month and
     * threshold at most.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Modules\Core\Finance\Models\Team\BudgetAlert, $this>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(BudgetAlert::class);
    }

    /**
     * Get the sub-budgets nested under the period, which only
     * a category period has.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Modules\Core\Finance\Models\Team\BudgetPeriod, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
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
     * Get the category period the budget is nested under,
     * when it is a sub-budget.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Finance\Models\Team\BudgetPeriod, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
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
     * Get the team the budget belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Identity\Models\Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get what the budget measures: the category it caps or
     * the tag it follows.
     */
    public function target(): Category|Tag|null
    {
        return $this->category instanceof Category ? $this->category : $this->tag;
    }

    /**
     * Get which kind of target the budget follows, taken from
     * the column the period was written with.
     */
    public function targetKind(): BudgetTargetKind
    {
        return $this->category instanceof Category
            ? BudgetTargetKind::Category
            : BudgetTargetKind::Tag;
    }

    /**
     * Whether the period is a sub-budget: a tag period nested
     * under a category period.
     */
    public function isNested(): bool
    {
        return $this->getAttribute($this->parent()->getForeignKeyName()) !== null;
    }

    /**
     * Get the category the period's figures are confined to:
     * its own for a category period, its parent's for a
     * sub-budget, and none for a plain tag period.
     */
    public function scopedCategory(): ?Category
    {
        if ($this->category instanceof Category) {
            return $this->category;
        }

        // A sub-budget only counts the tag's movements filed
        // under the category of the budget it sits in.
        if ($this->isNested()) {
            $this->loadMissing('parent');

            return $this->parent?->category;
        }

        return null;
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
