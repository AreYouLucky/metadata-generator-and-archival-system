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
        Schema::create('media_streams', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('media_file_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('stream_index');
            $table->string('type', 16);
            $table->string('codec')->nullable();
            $table->string('codec_long')->nullable();
            $table->string('profile')->nullable();
            $table->unsignedBigInteger('bit_rate')->nullable();
            $table->string('language', 16)->nullable();
            $table->decimal('duration_sec', 12, 3)->nullable();

            // Video
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('display_aspect_ratio', 16)->nullable();
            $table->string('frame_rate', 32)->nullable();
            $table->string('scan_type', 16)->nullable();
            $table->string('pix_fmt', 32)->nullable();
            $table->string('chroma_subsampling', 16)->nullable();
            $table->string('color_space', 32)->nullable();
            $table->string('color_primaries', 32)->nullable();
            $table->string('color_transfer', 32)->nullable();
            $table->string('color_range', 16)->nullable();

            // Video and audio
            $table->unsignedTinyInteger('bit_depth')->nullable();

            // Audio
            $table->unsignedTinyInteger('channels')->nullable();
            $table->string('channel_layout', 32)->nullable();
            $table->unsignedInteger('sample_rate')->nullable();

            $table->timestamps();

            $table->unique(['media_file_id', 'stream_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_streams');
    }
};
