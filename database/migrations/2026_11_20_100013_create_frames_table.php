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
        Schema::create('frames', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->decimal('time_sec', 12, 3);
            $table->string('path');
            $table->boolean('is_scene_start')->default(false);
            $table->text('description')->nullable();
            $table->text('ocr_text')->nullable();
            $table->jsonb('objects')->nullable();
            $table->unsignedSmallInteger('face_count')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'time_sec']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('frames');
    }
};
