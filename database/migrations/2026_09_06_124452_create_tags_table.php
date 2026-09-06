<?php

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
        Schema::create('tags', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->boolean('is_active')->default(true);
            $table->string('name');
            $table->foreignIdFor(Team::class)->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique([(new Team)->getForeignKey(), 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
