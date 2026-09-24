<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Models\Team;

use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetAlertKind;
use App\Modules\Support\Concerns\DefinesKeyType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The record that a budget's alert went out for a month, so
 * the same threshold is not emailed twice in it. Dropping the
 * record re-arms the alert.
 *
 * @property string $id
 * @property string $budget_period_id
 * @property \App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetAlertKind $kind
 * @property \Carbon\CarbonImmutable $month
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Modules\Core\Finance\Models\Team\BudgetPeriod $budgetPeriod
 *
 * @method static \Database\Factories\Modules\Core\Finance\Models\Team\BudgetAlertFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\BudgetAlert newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\BudgetAlert newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\BudgetAlert query()
 *
 * @mixin \Eloquent
 */
#[Fillable('kind', 'month')]
class BudgetAlert extends Model
{
    /** @use \App\Modules\Support\Concerns\DefinesKeyType<string> */
    use DefinesKeyType;

    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\Modules\Core\Finance\Models\Team\BudgetAlertFactory> */
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
            'kind' => BudgetAlertKind::class,
            'month' => 'immutable_date:Y-m-d',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // An alert belongs to a whole month, so the month is
        // pinned to its first day however it was handed in.
        static::saving(function (self $alert): void {
            $alert->month = $alert->month->startOfMonth();
        });
    }

    /**
     * Get the budget period the alert was sent for.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Finance\Models\Team\BudgetPeriod, $this>
     */
    public function budgetPeriod(): BelongsTo
    {
        return $this->belongsTo(BudgetPeriod::class);
    }
}
