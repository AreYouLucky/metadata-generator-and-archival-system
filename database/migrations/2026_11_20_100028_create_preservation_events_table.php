<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The preservation log is append-only and must outlive the records it
 * describes (a purge is itself logged), so project and file IDs are stored
 * without foreign key constraints.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('preservation_events', function (Blueprint $table) {
            $table->id();
            $table->ulid('project_id')->nullable();
            $table->ulid('media_file_id')->nullable();
            $table->string('type', 24);
            $table->string('outcome', 16);
            $table->text('detail')->nullable();
            $table->string('agent_type', 16);
            $table->string('agent')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['project_id', 'occurred_at']);
            $table->index('media_file_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('preservation_events');
    }
};
