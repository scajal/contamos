<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Models\Team;

use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Concerns\DefinesKeyType;
use App\Modules\Support\Concerns\HasTags;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string|null $description
 * @property \Carbon\CarbonImmutable $occurred_at
 * @property string $team_id
 * @property string|null $user_id
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Finance\Models\Team\Tag> $tags
 * @property-read \App\Modules\Core\Identity\Models\Team $team
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Finance\Models\Team\Transaction> $transactions
 * @property-read \App\Modules\Core\Identity\Models\User|null $user
 *
 * @method static \Database\Factories\Modules\Core\Finance\Models\Team\TransferFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\Transfer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\Transfer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\Transfer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\Transfer withAnyTags(array<int, string>|string $ids)
 *
 * @mixin \Eloquent
 */
#[Fillable('description', 'occurred_at')]
class Transfer extends Model
{
    /** @use \App\Modules\Support\Concerns\DefinesKeyType<string> */
    use DefinesKeyType;

    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\Modules\Core\Finance\Models\Team\TransferFactory> */
    use HasFactory;

    use HasTags;
    use HasUlids;

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * Get the fee charged on the destination account
     * of the transfer.
     */
    public function destinationFee(): ?Transaction
    {
        return $this->fee($this->destinationLeg());
    }

    /**
     * Get the leg of the transfer that credits the
     * destination account.
     */
    public function destinationLeg(): ?Transaction
    {
        return $this->leg(TransactionType::TransferIn);
    }

    /**
     * Get the fee sitting on the same account as the
     * given leg of the transfer.
     */
    protected function fee(?Transaction $leg): ?Transaction
    {
        $foreignKey = new Transaction()->account()->getForeignKeyName();

        // A fee always sits on an account, so it never matches a
        // side the transfer has no leg for.
        return $this->transactions
            ->first(fn (Transaction $transaction): bool => $transaction->type === TransactionType::Expense
                && $transaction->getAttribute($foreignKey) === $leg?->getAttribute($foreignKey));
    }

    /**
     * Get the leg of the transfer of the
     * given type.
     */
    protected function leg(TransactionType $type): ?Transaction
    {
        return $this->transactions->first(fn (Transaction $transaction): bool => $transaction->type === $type);
    }

    /**
     * Get the fee charged on the origin account
     * of the transfer.
     */
    public function originFee(): ?Transaction
    {
        return $this->fee($this->originLeg());
    }

    /**
     * Get the leg of the transfer that debits the
     * origin account.
     */
    public function originLeg(): ?Transaction
    {
        return $this->leg(TransactionType::TransferOut);
    }

    /**
     * Get the team that owns the transfer.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Identity\Models\Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the transactions owned by the transfer.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Modules\Core\Finance\Models\Team\Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the user that recorded the transfer.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Identity\Models\User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
