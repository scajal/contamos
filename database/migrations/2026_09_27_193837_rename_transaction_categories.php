<?php

use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Core\Finance\Models\Team\Transaction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The old category values and the ones that replaced them
     * when the list was redrawn around everyday life. Every
     * other value kept its name.
     *
     * @var array<string, string>
     */
    protected array $renames = [
        'family_and_pets' => 'kids_and_family',
        'financial' => 'taxes_and_fees',
        'food' => 'groceries',
        'gift' => 'gifts_received',
        'investments' => 'investment_returns',
        'leisure_and_travel' => 'entertainment',
        'reimbursement' => 'refunds',
        'subscriptions_and_services' => 'subscriptions',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->rename($this->renames);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->rename(array_flip($this->renames));
    }

    /**
     * Rewrite the category column of every table that carries
     * one, from each key of the map to its value.
     *
     * @param  array<string, string>  $map
     */
    protected function rename(array $map): void
    {
        $tables = [
            new Transaction()->getTable(),
            new RecurringRule()->getTable(),
            new BudgetPeriod()->getTable(),
        ];

        foreach ($tables as $table) {
            foreach ($map as $from => $to) {
                DB::table($table)->where('category', $from)->update(['category' => $to]);
            }
        }
    }
};
