<?php

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
        Schema::create('projects', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('work_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('part_number')->nullable();
            $table->string('part_label')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('catalogue_event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('accession_number')->nullable()->unique();
            $table->string('title');

            $table->string('status', 16)->default('queued');
            $table->string('review_status', 24)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->string('source_type', 16);
            $table->text('source_url')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('audio_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();

            // Summary of the master file for fast listing; the full record lives in media_files.
            $table->decimal('duration_sec', 12, 3)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->decimal('fps', 8, 3)->nullable();
            $table->string('video_codec')->nullable();
            $table->string('audio_codec')->nullable();
            $table->unsignedBigInteger('bitrate')->nullable();

            $table->string('language', 16)->nullable();
            $table->decimal('language_confidence', 5, 4)->nullable();
            $table->jsonb('options')->nullable();
            $table->text('selected_title')->nullable();
            $table->unsignedBigInteger('thumbnail_frame_id')->nullable();
            $table->string('checksum_status', 16)->default('pending');
            $table->text('error')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at']);
            $table->index(['review_status', 'published_at']);
            $table->index('status');
            $table->index('batch_id');
            $table->index('work_id');
            $table->index('category_id');
            $table->index('catalogue_event_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
