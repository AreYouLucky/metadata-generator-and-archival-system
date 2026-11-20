<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One transcript per language (the original plus any translations), each
 * made of timed segments.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('record_transcripts', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('record_id')->constrained()->cascadeOnDelete();
            $table->string('language', 16);
            $table->boolean('is_original')->default(true);
            $table->string('status', 16)->default('queued');
            $table->timestamps();

            $table->unique(['record_id', 'language']);
        });

        $supportsFullText = Schema::getConnection()->getDriverName() === 'pgsql';

        Schema::create('record_segments', function (Blueprint $table) use ($supportsFullText) {
            $table->id();
            $table->foreignId('record_transcript_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->decimal('start', 12, 3);
            $table->decimal('end', 12, 3);
            $table->text('text');
            $table->text('original_text');
            $table->string('speaker', 32)->nullable();
            $table->boolean('is_edited')->default(false);
            $table->timestamps();

            $table->index(['record_transcript_id', 'start']);
            $table->index(['record_transcript_id', 'position']);

            if ($supportsFullText) {
                $table->fullText('text');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('record_segments');
        Schema::dropIfExists('record_transcripts');
    }
};
