<?php

declare(strict_types=1);

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
        Schema::create('mcp_revoked_grants', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();

            // A revoked grant is matched by the session its tokens
            // carry, or, when WorkOS does not name one, by the client
            // the user revoked, for tokens issued before revoking.
            $table->string('session_id')->nullable()->index();
            $table->string('client_id')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'client_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mcp_revoked_grants');
    }
};
