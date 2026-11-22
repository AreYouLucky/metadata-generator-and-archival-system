<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 additions to records: the batch a record was uploaded in, the URL
 * it was imported from (yt-dlp), and the processing options chosen on upload,
 * kept so a URL import can start processing once the download finishes and a
 * retry can offer the same choices.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->foreignUlid('batch_id')->nullable()->after('created_by')->constrained()->nullOnDelete();
            $table->text('source_url')->nullable()->after('batch_id');
            $table->json('pipeline_options')->nullable()->after('processing_job_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('batch_id');
            $table->dropColumn(['source_url', 'pipeline_options']);
        });
    }
};
