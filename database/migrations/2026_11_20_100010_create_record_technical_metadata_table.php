<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How one record file is encoded. Filled automatically by the probe step
 * (ffprobe + mediainfo); read-only in the app.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('record_technical_metadata', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('record_file_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('file_format')->nullable();
            $table->decimal('duration', 12, 3)->nullable();
            $table->unsignedBigInteger('bitrate')->nullable();
            $table->string('start_timecode', 16)->nullable();

            // Primary video stream
            $table->string('video_codec')->nullable();
            $table->string('video_profile')->nullable();
            $table->string('resolution', 32)->nullable();
            $table->string('frame_rate', 32)->nullable();
            $table->string('aspect_ratio', 16)->nullable();
            $table->string('scan_type', 16)->nullable();
            $table->unsignedTinyInteger('bit_depth')->nullable();
            $table->string('chroma_subsampling', 16)->nullable();
            $table->string('color_space', 32)->nullable();
            $table->string('color_primaries', 32)->nullable();
            $table->string('color_transfer', 32)->nullable();

            // Primary audio stream, plus every audio track
            $table->string('audio_codec')->nullable();
            $table->unsignedTinyInteger('audio_channels')->nullable();
            $table->unsignedInteger('audio_sample_rate')->nullable();
            $table->jsonb('audio_tracks')->nullable();

            $table->jsonb('raw_probe')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('record_technical_metadata');
    }
};
