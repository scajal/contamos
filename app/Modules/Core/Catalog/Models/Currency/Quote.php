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

/**
 * @property string $id
 * @property string $currency_id
 * @property \Carbon\CarbonImmutable $observed_at
 * @property string $rate
 * @property string|null $recorded_by
 * @property string $source
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Modules\Core\Catalog\Models\Currency $currency
 * @property-read \App\Modules\Core\Identity\Models\User|null $recorder
 *
 * @method static \Database\Factories\Modules\Core\Catalog\Models\Currency\QuoteFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Catalog\Models\Currency\Quote newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Catalog\Models\Currency\Quote newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Catalog\Models\Currency\Quote query()
 *
 * @mixin \Eloquent
 */
#[Fillable('observed_at', 'rate', 'source')]
class Quote extends Model
{
    /** @use \App\Modules\Support\Concerns\DefinesKeyType<string> */
    use DefinesKeyType;

    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\Modules\Core\Catalog\Models\Currency\QuoteFactory> */
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
     * Get the currency that the quote is for.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Catalog\Models\Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Get the user that recorded the quote.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Identity\Models\User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
