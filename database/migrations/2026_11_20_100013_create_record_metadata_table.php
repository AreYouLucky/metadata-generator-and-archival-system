<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI-generated metadata for a record (title options, summary, tags, hashtags,
 * chapters, quotes...). Every generation is kept; one per record is active.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('record_metadata', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('record_id')->constrained()->cascadeOnDelete();
            $table->string('preset', 16);
            $table->text('custom_prompt')->nullable();
            $table->string('model');
            $table->jsonb('payload');
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index(['record_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('record_metadata');
    }
};
