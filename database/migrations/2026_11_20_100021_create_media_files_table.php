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
        Schema::create('media_files', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);
            $table->string('path');
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('container')->nullable();
            $table->string('container_long')->nullable();
            $table->decimal('duration_sec', 12, 3)->nullable();
            $table->unsignedBigInteger('bitrate')->nullable();
            $table->string('start_timecode', 16)->nullable();
            $table->char('md5', 32)->nullable();
            $table->char('sha256', 64)->nullable();
            $table->char('expected_md5', 32)->nullable();
            $table->char('expected_sha256', 64)->nullable();
            $table->string('checksum_status', 16)->default('pending');
            $table->jsonb('raw_probe')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
