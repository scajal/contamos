<?php

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
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
        Schema::create('budget_periods', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->decimal('amount', 36, 18);
            $table->foreignIdFor(Team::class)->constrained()->cascadeOnDelete();
            // A period follows a tag or a category, so each
            // target column is empty on the other kind.
            $table->foreignIdFor(Tag::class)->nullable()->constrained()->cascadeOnDelete();
            $table->string('category')->nullable();
            $table->foreignIdFor(Currency::class)->constrained();
            $table->date('from_month');
            $table->date('until_month')->nullable();
            $table->timestamps();

            $table->index([(new Tag)->getForeignKey(), (new Currency)->getForeignKey()]);
            $table->index([(new Team)->getForeignKey(), 'category', (new Currency)->getForeignKey()]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budget_periods');
    }
};
