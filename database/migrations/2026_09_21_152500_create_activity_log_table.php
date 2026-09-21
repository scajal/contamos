<?php

declare(strict_types=1);

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
        Schema::create('activity_log', function (Blueprint $table): void {
            // The activity log belongs to the package, and nothing
            // references its rows, so it keeps the package's own
            // auto-incrementing key instead of a ULID.
            $table->id();

            $table->string('log_name')->nullable()->index();
            $table->text('description');

            // Every domain model is ULID-keyed, so the morphs are
            // too. An integer-keyed subject stores its key as text
            // and is only ever read back through the relation.
            $table->nullableUlidMorphs('subject', 'subject');

            $table->string('event')->nullable();
            $table->nullableUlidMorphs('causer', 'causer');

            // One action may write several rows. They share a batch
            // so the timeline reads as what people did.
            $table->uuid('batch_uuid')->nullable()->index();

            // The team the entry belongs to, so its timeline is one
            // query and survives a deleted subject.
            $table->foreignIdFor(Team::class)->nullable()->index()->constrained()->cascadeOnDelete();

            $table->json('attribute_changes')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
