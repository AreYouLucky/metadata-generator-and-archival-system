<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A record is one archived video. File and encoding details live in
 * record_technical_metadata; keywords, subjects, contributors and rights
 * live in their own record_* tables.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('project_series_id')->nullable()->constrained('project_series')->nullOnDelete();
            $table->foreignId('agency_id')->nullable()->constrained('production_agencies')->nullOnDelete();
            $table->foreignId('division_id')->nullable()->constrained('production_agencies')->nullOnDelete();

            // Descriptive
            $table->string('title');
            $table->string('alternative_title')->nullable();
            $table->text('description')->nullable();
            $table->string('genre')->nullable();
            $table->string('video_type')->nullable();
            $table->string('language', 16)->nullable();
            $table->string('country_origin', 64)->nullable();
            $table->date('date_recorded')->nullable()->index();
            $table->date('date_published')->nullable();

            // Location
            $table->string('location')->nullable();
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('prov_id')->nullable()->constrained('provinces')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->foreignId('brgy_id')->nullable()->constrained('barangays')->nullOnDelete();

            // Stored file
            $table->string('storage_location')->nullable();
            $table->string('filename')->nullable();
            $table->string('thumbnail_path')->nullable();

            // Archival management
            $table->string('security_classification', 16)->default('unclassified');
            $table->string('retention_period')->nullable();
            $table->string('archival_value', 16)->nullable();

            // Workflow
            $table->string('processing_status', 16)->default('queued');
            $table->text('processing_error')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['created_by', 'created_at']);
            $table->index(['is_approved', 'date_recorded']);
            $table->index('project_id');
            $table->index('project_series_id');
            $table->index('agency_id');
            $table->index('region_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('records');
    }
};
