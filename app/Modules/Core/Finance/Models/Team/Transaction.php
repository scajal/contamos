<?php

namespace App\Modules\Core\Finance\Models\Team;

use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Concerns\DefinesKeyType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    /** @use \App\Modules\Support\Concerns\DefinesKeyType<string> */
    use DefinesKeyType;

    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\Modules\Core\Finance\Models\Team\TransactionFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * Get the account for the transaction.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Finance\Models\Team\Account>
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
        static::creating(function (Transaction $transaction) {
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
            'amount' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * Get the team for the transaction.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Identity\Models\Team>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user for the transaction.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Identity\Models\User>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
