<?php

namespace App\Modules\Core\Catalog\Models;

use App\Modules\Support\Concerns\DefinesKeyType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $code
 * @property int $decimal_places
 * @property string $name
 * @property string $symbol
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @method static \Database\Factories\Modules\Core\Catalog\Models\CurrencyFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Catalog\Models\Currency newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Catalog\Models\Currency newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Catalog\Models\Currency query()
 * @mixin \Eloquent
 */
#[Fillable('code', 'decimal_places', 'name', 'symbol')]
class Currency extends Model
{
    /** @use \App\Modules\Support\Concerns\DefinesKeyType<string> */
    use DefinesKeyType;

    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\Modules\Core\Catalog\Models\CurrencyFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Callback executed when a currency is
        // being created.
        static::creating(function (self $currency): void {
            $currency->code = Str::upper($currency->code);
        });

        // Callback executed when a currency is
        // being updated.
        static::updating(function (self $currency): void {
            $currency->code = Str::upper($currency->code);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
        ];
    }
}
