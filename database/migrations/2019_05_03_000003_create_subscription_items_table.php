<?php

use App\Modules\Billing\Models\Subscription;
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
        Schema::create('billing_subscription_items', function (Blueprint $table): void {
            $table->id();
            $table->string('price_id');
            $table->string('product_id');
            $table->integer('quantity');
            $table->string('status');
            $table->foreignIdFor(Subscription::class);
            $table->timestamps();

            $table->unique([new Subscription()->getForeignKey(), 'price_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_items');
    }
};
