<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clips (Phase 7): a part of the record's master cut to its own MP4 by the
 * AI service, then checksummed and probed like every other file. Kept at
 * `records/{record}/clips/{id}.mp4` on the private media disk; not an
 * archival file, so not in `record_files` (see DECISIONS.md).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clips', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 200)->nullable();
            $table->double('start');
            $table->double('end');
            $table->string('status', 16)->default('queued');
            $table->string('path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->char('md5_checksum', 32)->nullable();
            $table->char('sha256_checksum', 64)->nullable();
            $table->json('technical')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['record_id', 'id']);
            $table->index('created_by');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clips');
    }
};
