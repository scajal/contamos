<?php

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
        Schema::create('billing_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->morphs('billable');
            $table->timestamp('ends_at')->nullable();
            $table->string('paddle_id')->unique();
            $table->timestamp('paused_at')->nullable();
            $table->string('status');
            $table->timestamp('trial_ends_at')->nullable();
            $table->string('type');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
