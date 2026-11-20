<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visual analysis: key frames (description, on-screen text, objects and a
 * face count, never face recognition) and shot/scene segments with timecode.
 * Also links records.thumbnail_frame_id now that frames exist.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('record_frames', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('record_id')->constrained()->cascadeOnDelete();
            $table->decimal('time_sec', 12, 3);
            $table->string('path');
            $table->boolean('is_scene_start')->default(false);
            $table->text('description')->nullable();
            $table->text('ocr_text')->nullable();
            $table->jsonb('objects')->nullable();
            $table->unsignedSmallInteger('face_count')->nullable();
            $table->timestamps();

            $table->index(['record_id', 'time_sec']);
        });

        Schema::create('record_scenes', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('record_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 8)->default('shot');
            $table->decimal('start', 12, 3);
            $table->decimal('end', 12, 3);
            $table->string('tc_in', 16)->nullable();
            $table->string('tc_out', 16)->nullable();
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['record_id', 'start']);
        });

        Schema::table('records', function (Blueprint $table) {
            $table->foreign('thumbnail_frame_id')->references('id')->on('record_frames')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->dropForeign(['thumbnail_frame_id']);
        });

        Schema::dropIfExists('record_scenes');
        Schema::dropIfExists('record_frames');
    }
};
