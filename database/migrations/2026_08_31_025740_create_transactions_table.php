<?php

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Identity\Models\Team;
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
        Schema::create('transactions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignIdFor(Account::class)->constrained()->cascadeOnDelete();
            $table->decimal('amount', 36, 18);
            $table->foreignIdFor(Currency::class)->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->text('description')->nullable();
            $table->dateTime('occurred_at')->useCurrent();
            $table->decimal('quote', 36, 18);
            $table->foreignIdFor(Team::class)->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
