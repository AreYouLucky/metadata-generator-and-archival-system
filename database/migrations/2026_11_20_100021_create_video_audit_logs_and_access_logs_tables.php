<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two append-only logs. video_audit_logs records everything done to a record
 * or its files (uploaded, approved, transcoded, deleted...), including review
 * notes; access_logs records who viewed or downloaded it. Both must outlive
 * the record, so they keep its ID without a foreign key constraint.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('video_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->ulid('record_id');
            $table->ulid('record_file_id')->nullable();
            $table->string('action', 32);
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->timestamp('performed_at');
            $table->text('remarks')->nullable();

            $table->index(['record_id', 'performed_at']);
            $table->index('performed_by');
        });

        Schema::create('access_logs', function (Blueprint $table) {
            $table->id();
            $table->ulid('record_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 16);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('accessed_at');

            $table->index(['record_id', 'accessed_at']);
            $table->index(['user_id', 'accessed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_logs');
        Schema::dropIfExists('video_audit_logs');
    }
};
