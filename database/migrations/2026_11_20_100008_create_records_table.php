<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A record is one archived video. Its files live in record_files (with
 * technical metadata per file); credits, rights, subjects, keywords,
 * transcripts, frames, scenes, provenance and attachments live in their own
 * record_* tables.
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
            $table->string('accession_number')->nullable()->unique();

            // Grouping
            $table->foreignUlid('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('project_series_id')->nullable()->constrained('project_series')->nullOnDelete();
            $table->foreignUlid('work_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('part_number')->nullable();
            $table->string('part_label')->nullable();
            $table->foreignId('agency_id')->nullable()->constrained('production_agencies')->nullOnDelete();
            $table->foreignId('division_id')->nullable()->constrained('production_agencies')->nullOnDelete();

            // Descriptive
            $table->string('title');
            $table->string('alternative_title')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('genre_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('video_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('language', 16)->nullable();
            $table->decimal('language_confidence', 5, 4)->nullable();
            $table->string('country_origin', 64)->nullable();
            $table->date('date_recorded')->nullable()->index();
            $table->string('date_recorded_precision', 8)->nullable();
            $table->string('date_recorded_source', 16)->nullable();
            $table->date('date_published')->nullable();
            $table->unsignedSmallInteger('season_number')->nullable();
            $table->unsignedSmallInteger('episode_number')->nullable();

            // Location
            $table->string('location')->nullable();
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('prov_id')->nullable()->constrained('provinces')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->foreignId('brgy_id')->nullable()->constrained('barangays')->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('location_source', 16)->nullable();

            // Thumbnail: an uploaded image, or one of the record's frames (FK added with record_frames)
            $table->string('thumbnail_path')->nullable();
            $table->unsignedBigInteger('thumbnail_frame_id')->nullable();

            // Archival management
            $table->string('security_classification', 16)->default('unclassified');
            $table->string('retention_period')->nullable();
            $table->string('archival_value', 16)->nullable();
            $table->date('disposition_date')->nullable()->index();
            $table->string('disposition_action', 16)->nullable();

            // Processing
            $table->string('processing_status', 16)->default('queued');
            $table->text('processing_error')->nullable();

            // Approval
            $table->string('review_status', 24)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['created_by', 'created_at']);
            $table->index(['review_status', 'published_at']);
            $table->index('project_id');
            $table->index('project_series_id');
            $table->index('work_id');
            $table->index('agency_id');
            $table->index('genre_id');
            $table->index('video_type_id');
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
