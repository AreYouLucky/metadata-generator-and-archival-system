<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Private files attached to a record: release forms, contracts, sidecar
 * files, original subtitle files. Stored on a private disk only.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('record_attachments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 16);
            $table->string('path');
            $table->string('original_filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->char('sha256_checksum', 64);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['record_id', 'kind']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('record_attachments');
    }
};
