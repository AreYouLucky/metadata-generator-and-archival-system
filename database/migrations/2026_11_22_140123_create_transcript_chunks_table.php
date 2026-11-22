<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transcript passages for chat search (Phase 7): ~45 s windows of the
 * original transcript with their embedding. The host PostgreSQL has no
 * pgvector, and search is always within one record (a few hundred passages),
 * so the vector is stored as base64 float32 and compared in PHP (see
 * DECISIONS.md). `text_hash` lets an edited transcript re-embed only the
 * passages that changed.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transcript_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('record_transcript_id')->constrained()->cascadeOnDelete();
            $table->float('start');
            $table->float('end');
            $table->text('text');
            $table->char('text_hash', 64);
            $table->text('embedding')->nullable();
            $table->string('embedding_model', 100)->nullable();
            $table->unsignedSmallInteger('embedding_dimensions')->nullable();
            $table->timestamps();

            $table->index(['record_id', 'start']);
            $table->index('record_transcript_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transcript_chunks');
    }
};
