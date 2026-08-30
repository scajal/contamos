<?php

namespace App\Modules\Core\Finance\Models;

use App\Modules\Core\Finance\Enums\Account\AccountType;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Support\Concerns\DefinesKeyType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable('name', 'type')]
class Account extends Model
{
    /** @use \App\Modules\Support\Concerns\DefinesKeyType<string> */
    use DefinesKeyType;

    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\Modules\Finance\Models\AccountFactory> */
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
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Team, Account>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
