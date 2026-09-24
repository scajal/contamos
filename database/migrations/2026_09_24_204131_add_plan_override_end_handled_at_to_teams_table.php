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
        Schema::table('teams', function (Blueprint $table): void {
            // When the end of the plan granted by hand was dealt
            // with, so its Owner hears about the drop once.
            $table->timestamp('plan_override_end_handled_at')->nullable()->after('plan_override_expires_at');
        });

        // Grants that lapsed before this column existed were
        // never announced, and announcing them now would be
        // stale news.
        DB::table('teams')
            ->whereNotNull('plan_override')
            ->where('plan_override_expires_at', '<=', CarbonImmutable::now())
            ->update(['plan_override_end_handled_at' => CarbonImmutable::now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->dropColumn('plan_override_end_handled_at');
        });
    }
};
