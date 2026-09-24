<?php

namespace App\Modules\Core\Finance\Models\Team;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Concerns\DefinesKeyType;
use App\Modules\Support\Concerns\HasTags;
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
 * @property string $id
 * @property string $account_id
 * @property numeric $amount
 * @property \App\Modules\Core\Finance\Enums\Transaction\Category|null $category
 * @property string $currency_id
 * @property int $decimal_places
 * @property string|null $description
 * @property \Carbon\CarbonImmutable|null $due_date
 * @property \Carbon\CarbonImmutable $occurred_at
 * @property numeric $quote
 * @property string|null $recurring_rule_id
 * @property string $team_id
 * @property \App\Modules\Core\Finance\Enums\Transaction\TransactionType $type
 * @property string|null $transfer_id
 * @property string|null $user_id
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Modules\Core\Finance\Models\Team\Account $account
 * @property-read \App\Modules\Core\Catalog\Models\Currency $currency
 * @property-read \App\Modules\Core\Finance\Models\Team\RecurringRule|null $recurringRule
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Finance\Models\Team\Tag> $tags
 * @property-read \App\Modules\Core\Identity\Models\Team $team
 * @property-read \App\Modules\Core\Finance\Models\Team\Transfer|null $transfer
 * @property-read \App\Modules\Core\Identity\Models\User|null $user
 *
 * @method static \Database\Factories\Modules\Core\Finance\Models\Team\TransactionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\Transaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\Transaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\Transaction occurredFrom(string $date)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\Transaction ofType(string $type)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\Transaction occurredUntil(string $date)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\Transaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\Transaction withAnyTags(array<int, string>|string $ids)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\Transaction withinHistoryWindow(?\Carbon\CarbonImmutable $since)
 *
 * @mixin \Eloquent
 */
#[Fillable('amount', 'category', 'decimal_places', 'description', 'due_date', 'occurred_at', 'quote', 'type')]
class Transaction extends Model
{
    /**
     * The type filter value matching both legs of a transfer.
     */
    public const string TRANSFER_TYPE = 'transfer';

    /** @use \App\Modules\Support\Concerns\DefinesKeyType<string> */
    use DefinesKeyType;

    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\Modules\Core\Finance\Models\Team\TransactionFactory> */
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
            'occurred_at',
            'quote',
            'type',
        ];
    }

    /**
     * Get the key of the team the transaction belongs to.
     */
    public function activityTeamKey(): ?string
    {
        return is_string($key = $this->getAttribute($this->team()->getForeignKeyName())) ? $key : null;
    }

    /**
     * Get the account for the transaction.
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

        // Callback executed when a transaction is being created.
        static::creating(function (Transaction $transaction): void {
            $transaction->{$transaction->team()->getForeignKeyName()} ??= Account::query()
                ->whereKey($transaction->getAttribute($transaction->account()->getForeignKeyName()))
                ->value($transaction->team()->getForeignKeyName());
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
            'due_date' => 'date',
            'occurred_at' => 'datetime',
            'quote' => 'decimal:18',
            'type' => TransactionType::class,
        ];
    }

    /**
     * Get the currency for the transaction.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Catalog\Models\Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Scope the query to transactions of the given type, where the
     * transfer value matches both of its legs.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Modules\Core\Finance\Models\Team\Transaction>  $query
     */
    #[Scope]
    protected function ofType(Builder $query, string $type): void
    {
        $query->when(
            $type === self::TRANSFER_TYPE,
            fn (Builder $query): Builder => $query->whereIn('type', TransactionType::transferCases()),
            fn (Builder $query): Builder => $query->where('type', $type),
        );
    }

    /**
     * The first day a team on a limited plan may still see, given
     * how many months its plan keeps visible. The window is that
     * many calendar months ending on the current one, so a limit
     * of six opens the current month and the five before it. A
     * null limit means unlimited history and no window.
     */
    public static function historyWindowStart(?int $months): ?CarbonImmutable
    {
        return $months === null
            ? null
            : CarbonImmutable::now()->startOfMonth()->subMonths($months - 1);
    }

    /**
     * Whether the transaction is a leg or a fee of a transfer,
     * which is only ever edited or deleted through the transfer.
     */
    public function belongsToTransfer(): bool
    {
        return filled($this->getAttribute($this->transfer()->getForeignKeyName()));
    }

    /**
     * Whether the transaction falls inside the history window
     * that starts on the given day. A null start means the plan
     * keeps everything, so every transaction is inside it.
     */
    public function isWithinHistoryWindow(?CarbonImmutable $since): bool
    {
        return ! $since instanceof CarbonImmutable || ! $this->occurred_at->startOfDay()->lessThan($since);
    }

    /**
     * Scope the query to transactions inside the history window
     * that starts on the given day, with the same date-only
     * comparison the date filters use. A null start leaves the
     * query untouched.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Modules\Core\Finance\Models\Team\Transaction>  $query
     */
    #[Scope]
    protected function withinHistoryWindow(Builder $query, ?CarbonImmutable $since): void
    {
        $query->when(
            $since instanceof CarbonImmutable,
            fn (Builder $query): Builder => $query->whereDate('occurred_at', '>=', $since?->toDateString()),
        );
    }

    /**
     * Get the values the transactions index type filter accepts, where
     * the transfer value matches both legs of a transfer.
     *
     * @return array<int, string>
     */
    public static function typeFilters(): array
    {
        return [
            ...array_column(TransactionType::recordableCases(), 'value'),
            TransactionType::OpeningBalance->value,
            self::TRANSFER_TYPE,
        ];
    }

    /**
     * Get the recurring rule the transaction was generated from.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Finance\Models\Team\RecurringRule, $this>
     */
    public function recurringRule(): BelongsTo
    {
        return $this->belongsTo(RecurringRule::class);
    }

    /**
     * Scope the query to transactions that occurred on or after the given date.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Modules\Core\Finance\Models\Team\Transaction>  $query
     */
    #[Scope]
    protected function occurredFrom(Builder $query, string $date): void
    {
        $query->whereDate('occurred_at', '>=', $date);
    }

    /**
     * Scope the query to transactions that occurred on or before the given date.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Modules\Core\Finance\Models\Team\Transaction>  $query
     */
    #[Scope]
    protected function occurredUntil(Builder $query, string $date): void
    {
        $query->whereDate('occurred_at', '<=', $date);
    }

    /**
     * Get the team for the transaction.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Identity\Models\Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the transfer that owns the transaction.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Finance\Models\Team\Transfer, $this>
     */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    /**
     * Get the user for the transaction.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Identity\Models\User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
