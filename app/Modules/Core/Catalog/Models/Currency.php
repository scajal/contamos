<?php

namespace App\Modules\Core\Catalog\Models;

use App\Modules\Core\Catalog\Enums\Currency\CurrencyType;
use App\Modules\Core\Catalog\Enums\Currency\Quote\QuoteSource;
use App\Modules\Core\Catalog\Models\Currency\Quote;
use App\Modules\Support\Concerns\DefinesKeyType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $code
 * @property int $decimal_places
 * @property bool $is_active
 * @property string $name
 * @property \App\Modules\Core\Catalog\Enums\Currency\Quote\QuoteSource $quote_source
 * @property string $symbol
 * @property \App\Modules\Core\Catalog\Enums\Currency\CurrencyType $type
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Modules\Core\Catalog\Models\Currency\Quote|null $currentQuote
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Catalog\Models\Currency\Quote> $quotes
 *
 * @method static \Database\Factories\Modules\Core\Catalog\Models\CurrencyFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Catalog\Models\Currency isActive(string|bool $value = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Catalog\Models\Currency newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Catalog\Models\Currency newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Catalog\Models\Currency query()
 *
 * @mixin \Eloquent
 */
#[Fillable('code', 'decimal_places', 'is_active', 'name', 'quote_source', 'symbol', 'type')]
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
            'is_active' => 'boolean',
            'quote_source' => QuoteSource::class,
            'type' => CurrencyType::class,
        ];
    }

    /**
     * Get the current quote for the currency.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<\App\Modules\Core\Catalog\Models\Currency\Quote, $this>
     */
    public function currentQuote(): HasOne
    {
        return $this
            ->hasOne(Quote::class)
            ->ofMany(['observed_at' => 'max'], fn (Builder $query): Builder => $query->where('observed_at', '<=', CarbonImmutable::now()));
    }

    /**
     * Scope the query to currencies with the given active state.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     */
    #[Scope]
    protected function isActive(Builder $query, bool|string $value = true): void
    {
        $query->where('is_active', filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }

    /**
     * Get the quotes for the currency.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Modules\Core\Catalog\Models\Currency\Quote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }
}
