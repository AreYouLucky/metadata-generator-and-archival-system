<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * YouTube publications (Phase 7): each attempt to publish a record, what was
 * sent (title, description, tags, privacy) and how it went. The resumable
 * upload session lets an interrupted upload continue; YouTube's own upload
 * and processing status is polled until the video is processed.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('youtube_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Kept when the channel is disconnected, so the history stays.
            $table->foreignId('youtube_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel_id', 64);
            $table->string('channel_title', 200);
            $table->string('status', 16)->default('queued');
            $table->string('privacy_status', 16);
            $table->string('title', 100);
            $table->text('description');
            $table->json('tags');
            $table->string('category_id', 8);
            $table->boolean('made_for_kids')->default(false);
            $table->boolean('with_thumbnail')->default(true);
            $table->text('upload_session_url')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedBigInteger('bytes_uploaded')->default(0);
            $table->string('video_id', 32)->nullable();
            $table->string('youtube_upload_status', 32)->nullable();
            $table->string('youtube_processing_status', 32)->nullable();
            $table->string('thumbnail_error', 500)->nullable();
            $table->string('error', 1000)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('status_checked_at')->nullable();
            $table->timestamps();

            $table->index(['record_id', 'id']);
            $table->index('user_id');
            $table->index('youtube_account_id');
            $table->index('status');
            $table->index('video_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('youtube_publications');
    }
};
