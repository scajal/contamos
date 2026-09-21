<?php

use App\Modules\Core\Finance\Models\Team\RecurringRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            // Alphabetically, due_date falls between the existing description
            // and occurred_at columns, and recurring_rule_id between quote
            // and team_id.
            $table->date('due_date')->nullable()->after('description');
            $table->foreignIdFor(RecurringRule::class)->nullable()->after('quote')->constrained()->nullOnDelete();
            $table->unique([new RecurringRule()->getForeignKey(), 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropUnique(['recurring_rule_id', 'due_date']);
            $table->dropConstrainedForeignIdFor(RecurringRule::class);
            $table->dropColumn('due_date');
        });
    }
};
