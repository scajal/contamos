<?php

namespace App\Modules\Core\Catalog\Models\Currency;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Concerns\DefinesKeyType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable('observed_at', 'rate')]
class ExchangeRate extends Model
{
    /** @use \App\Modules\Support\Concerns\DefinesKeyType<ExchangeRate> */
    use DefinesKeyType;

    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\App\Modules\Core\Catalog\Models\Currency\ExchangeRate> $factory */
    use HasFactory;

    use HasUlids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
            'rate' => 'decimal:18',
        ];
    }

    /**
     * Get the currency that the exchange rate is for.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Catalog\Models\Currency, \App\Modules\Core\Catalog\Models\Currency\ExchangeRate>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Get the user that recorded the exchange rate.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Identity\Models\User, \App\Modules\Core\Catalog\Models\Currency\ExchangeRate>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
