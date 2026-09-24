<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('billing_subscriptions', function (Blueprint $table): void {
            // When the end of the subscription was dealt with, so
            // a retried webhook never emails its Owner twice.
            $table->timestamp('end_handled_at')->nullable()->after('ends_at');
        });

        // Subscriptions that ended before this column existed
        // were never announced, and a late retry of their
        // webhook should not announce them now.
        DB::table('billing_subscriptions')
            ->where('status', 'canceled')
            ->update(['end_handled_at' => CarbonImmutable::now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('billing_subscriptions', function (Blueprint $table): void {
            $table->dropColumn('end_handled_at');
        });
    }
};
