<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every stored file of a record: the master, a mezzanine copy, the
 * browser-playable proxy, an access copy. Each carries its own checksums.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('record_files', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('record_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);
            $table->string('storage_location');
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->char('md5_checksum', 32)->nullable();
            $table->char('sha256_checksum', 64)->nullable()->index();
            $table->char('expected_md5', 32)->nullable();
            $table->char('expected_sha256', 64)->nullable();
            $table->string('checksum_status', 16)->default('pending');
            $table->timestamps();

            $table->index(['record_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('record_files');
    }
};
