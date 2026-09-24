<?php

declare(strict_types=1);

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
            // The share of the month's pot, from 1 to 99, at
            // which the team wants to be warned.
            $table->unsignedTinyInteger('warning_threshold')->nullable()->after('until_month');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('budget_periods', function (Blueprint $table): void {
            $table->dropColumn('warning_threshold');
        });
    }
};
