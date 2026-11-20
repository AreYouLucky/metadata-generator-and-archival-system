<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How the record's video file is encoded. Filled automatically by the probe
 * and checksum steps; read-only in the app.
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
            $table->foreignUlid('record_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('file_format')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->decimal('duration', 12, 3)->nullable();
            $table->string('video_codec')->nullable();
            $table->string('audio_codec')->nullable();
            $table->string('resolution', 32)->nullable();
            $table->string('frame_rate', 32)->nullable();
            $table->unsignedBigInteger('bitrate')->nullable();
            $table->string('aspect_ratio', 16)->nullable();
            $table->unsignedTinyInteger('audio_channels')->nullable();
            $table->unsignedInteger('audio_sample_rate')->nullable();
            $table->char('sha256_checksum', 64)->nullable()->index();
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
