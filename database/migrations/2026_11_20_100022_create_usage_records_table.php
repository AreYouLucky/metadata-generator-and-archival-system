<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Metered AI usage (transcription minutes, LLM tokens, vision frames,
 * storage) per encoder. Append-only; feeds the admin usage page and the
 * daily processing limits.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('record_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 32);
            $table->decimal('quantity', 16, 4);
            $table->decimal('estimated_cost', 12, 6)->nullable();
            $table->timestamp('occurred_at');

            $table->index(['user_id', 'occurred_at']);
            $table->index('record_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_records');
    }
};
