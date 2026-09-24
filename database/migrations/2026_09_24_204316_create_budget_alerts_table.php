<?php

declare(strict_types=1);

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
        Schema::create('budget_alerts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignIdFor(BudgetPeriod::class)->constrained()->cascadeOnDelete();
            // The first day of the month the alert was sent
            // for, and which of the thresholds it was.
            $table->date('month');
            $table->string('kind');
            $table->timestamps();

            // An alert is sent at most once per period, month
            // and threshold, which the index enforces even
            // when two writes race.
            $table->unique([(new BudgetPeriod)->getForeignKey(), 'month', 'kind']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budget_alerts');
    }
};
