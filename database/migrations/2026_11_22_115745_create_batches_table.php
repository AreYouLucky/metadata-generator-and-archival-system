<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batches (Phase 6): several files or URLs uploaded together, each becoming
 * its own record. The counts follow the records' processing status; the ZIP
 * of their exports is built on request. ULID keys, as batches appear in URLs.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('name', 120)->nullable();
            $table->string('status', 16)->default('processing');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('completed')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->string('zip_path')->nullable();
            $table->string('zip_status', 16)->nullable();
            $table->timestamp('zip_built_at')->nullable();
            $table->timestamps();

            $table->index(['created_by', 'created_at']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
