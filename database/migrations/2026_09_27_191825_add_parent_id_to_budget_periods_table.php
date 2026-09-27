<?php

use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('budget_periods', function (Blueprint $table): void {
            // A tag budget may sit under a category budget as one
            // of its sub-budgets. The parent going away leaves
            // the child as a plain tag budget.
            $table->foreignIdFor(BudgetPeriod::class, 'parent_id')
                ->nullable()
                ->after('category')
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('budget_periods', function (Blueprint $table): void {
            $table->dropConstrainedForeignIdFor(BudgetPeriod::class, 'parent_id');
        });
    }
};
