<?php

namespace App\Modules\Core\Finance\Models\Team;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Concerns\DefinesKeyType;
use App\Modules\Support\Concerns\HasTags;
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
 * @property string $currency_id
 * @property int $decimal_places
 * @property string|null $description
 * @property \Carbon\CarbonImmutable $occurred_at
 * @property numeric $quote
 * @property string $team_id
 * @property \App\Modules\Core\Finance\Enums\Transaction\TransactionType $type
 * @property string|null $transfer_id
 * @property string|null $user_id
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Modules\Core\Finance\Models\Team\Account $account
 * @property-read \App\Modules\Core\Catalog\Models\Currency $currency
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
 *
 * @mixin \Eloquent
 */
#[Fillable('amount', 'decimal_places', 'description', 'occurred_at', 'quote', 'type')]
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
            'decimal_places' => 'integer',
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
