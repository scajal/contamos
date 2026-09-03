<?php

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Identity\Models\User;
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
        Schema::create('quotes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignIdFor(Currency::class)->constrained()->cascadeOnDelete();
            $table->dateTime('observed_at');
            $table->decimal('rate', 36, 18);
            $table->foreignIdFor(User::class, 'recorded_by')->nullable()->constrained()->nullOnDelete();
            $table->string('source')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
