<?php

namespace App\Modules\Core\Finance\Models\Team;

use App\Modules\Core\Finance\Enums\Account\AccountType;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Support\Concerns\DefinesKeyType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $name
 * @property string $team_id
 * @property \App\Modules\Core\Finance\Enums\Account\AccountType $type
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Modules\Core\Identity\Models\Team $team
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\Account newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\Account newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\Account query()
 * @mixin \Eloquent
 */
#[Fillable('name', 'type')]
class Account extends Model
{
    /** @use \App\Modules\Support\Concerns\DefinesKeyType<string> */
    use DefinesKeyType;

    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\Modules\Core\Finance\Models\Team\AccountFactory> */
    use HasFactory;

    use HasUlids;

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
     * Get the team that owns the account.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Identity\Models\Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
