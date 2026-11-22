<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The AI service's id for the record's current pipeline run, so "Cancel"
 * can stop it (POST /jobs/{job_id}/cancel). Only read by record id, so no
 * index of its own.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->string('processing_job_id', 64)->nullable()->after('processing_error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->dropColumn('processing_job_id');
        });
    }
};
