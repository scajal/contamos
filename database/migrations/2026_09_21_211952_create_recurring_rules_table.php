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
    public function up(): void
    {
        Schema::create('recurring_rules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignIdFor(Account::class)->constrained()->cascadeOnDelete();
            $table->decimal('amount', 36, 18);
            $table->foreignIdFor(Currency::class)->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->text('description')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->string('frequency');
            $table->unsignedTinyInteger('interval')->default(1);
            $table->boolean('is_active')->default(true);
            $table->date('last_generated_due_date')->nullable();
            $table->date('starts_on');
            $table->foreignIdFor(Team::class)->constrained()->cascadeOnDelete();
            $table->time('time_of_day');
            $table->string('timezone');
            $table->string('type');
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_rules');
    }
};
