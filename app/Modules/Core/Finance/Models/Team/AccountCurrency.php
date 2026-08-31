<?php

namespace App\Modules\Core\Finance\Models\Team;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Support\Concerns\DefinesKeyType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class AccountCurrency extends Pivot
{
    /** @use \App\Modules\Support\Concerns\DefinesKeyType<int> */
    use DefinesKeyType;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * Get the account that the account currency belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Finance\Models\Team\Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the currency that the account currency belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Catalog\Models\Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
