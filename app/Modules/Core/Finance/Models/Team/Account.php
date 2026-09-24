<?php

namespace App\Modules\Core\Finance\Models\Team;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Account\AccountType;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Support\Concerns\DefinesKeyType;
use App\Modules\Support\Concerns\HasActivation;
use App\Modules\Support\Concerns\LogsTeamActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property bool $is_active
 * @property string $name
 * @property string $team_id
 * @property \App\Modules\Core\Finance\Enums\Account\AccountType $type
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Modules\Core\Finance\Models\Team\AccountCurrency|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Catalog\Models\Currency> $currencies
 * @property-read \App\Modules\Core\Identity\Models\Team $team
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Finance\Models\Team\Transaction> $transactions
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\Account balancesByAccountAndCurrency()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\Account balancesByCurrency()
 * @method static \Database\Factories\Modules\Core\Finance\Models\Team\AccountFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\Account isActive(string|bool $value = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\Account newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\Account newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\Account query()
 *
 * @mixin \Eloquent
 */
#[Fillable('is_active', 'name', 'type')]
class Account extends Model
{
    /** @use \App\Modules\Support\Concerns\DefinesKeyType<string> */
    use DefinesKeyType;

    use HasActivation;

    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\Modules\Core\Finance\Models\Team\AccountFactory> */
    use HasFactory;

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
        return ['is_active', 'name'];
    }

    /**
     * Get the key of the team the account belongs to.
     */
    public function activityTeamKey(): ?string
    {
        return is_string($key = $this->getAttribute($this->team()->getForeignKeyName())) ? $key : null;
    }

    /**
     * Scope the query to the aggregated balance per currency
     * of the accounts matching the query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     */
    #[Scope]
    protected function balancesByCurrency(Builder $query): void
    {
        // Build one CASE branch per transaction type, signing the amount accordingly.
        $signedAmount = collect(TransactionType::cases())
            ->map(fn (TransactionType $type): string => sprintf('WHEN %s = ? THEN %s%s', ($transaction = new Transaction)->qualifyColumn('type'), $type->sign(), $transaction->qualifyColumn('amount')))
            ->implode(' ');

        // For each currency, sum the signed amount of the transactions.
        $query
            ->select(($currency = new Currency)->qualifyColumns([$currency->getKeyName(), 'code', 'decimal_places', 'symbol']))
            ->selectRaw(sprintf('SUM(CASE %s ELSE 0 END) AS balance', $signedAmount), array_column(TransactionType::cases(), 'value')) // @phpstan-ignore-line argument.type Columns come from the models.
            ->join(($transaction = new Transaction)->getTable(), $transaction->qualifyColumn($this->getForeignKey()), '=', $this->getQualifiedKeyName())
            ->join($currency->getTable(), $currency->getQualifiedKeyName(), '=', $transaction->qualifyColumn($currency->getForeignKey()))
            ->groupBy($currency->qualifyColumns([$currency->getKeyName(), 'code', 'decimal_places', 'symbol']))
            ->orderBy($currency->qualifyColumn('code'));
    }

    /**
     * Scope the query to the balance per currency of each
     * account matching the query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     */
    #[Scope]
    protected function balancesByAccountAndCurrency(Builder $query): void
    {
        // Sum the balances the way the dashboard does.
        $query->balancesByCurrency();

        // Split each currency's sum by the account it belongs to.
        $query
            ->addSelect(sprintf('%s as %s', $this->getQualifiedKeyName(), $this->getForeignKey()))
            ->groupBy($this->getQualifiedKeyName());
    }

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
        ];
    }

    /**
     * Get the currencies for the account.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Modules\Core\Catalog\Models\Currency, $this, \App\Modules\Core\Finance\Models\Team\AccountCurrency, 'pivot'>
     */
    public function currencies(): BelongsToMany
    {
        return $this->belongsToMany(Currency::class, new AccountCurrency()->getTable(), $this->getForeignKey(), new Currency()->getForeignKey())
            ->using(AccountCurrency::class)
            ->withPivot(['balance'])
            ->withTimestamps();
    }

    /**
     * Get the team that owns the account.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Identity\Models\Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the transactions for the account.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Modules\Core\Finance\Models\Team\Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
