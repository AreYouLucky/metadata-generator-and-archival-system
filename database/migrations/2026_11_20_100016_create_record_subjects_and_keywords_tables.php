<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subjects (who or what the video is about) and keywords, many per record.
 * Anything suggested by AI, OCR or the transcript stays unconfirmed until an
 * encoder accepts it; only confirmed entries appear publicly.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('record_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('record_id')->constrained()->cascadeOnDelete();
            $table->string('subject')->index();
            $table->string('type', 16)->default('topic');
            $table->string('source', 16)->default('manual');
            $table->boolean('is_confirmed')->default(false);
            $table->timestamps();

            $table->unique(['record_id', 'subject']);
            $table->index(['record_id', 'is_confirmed']);
        });

        Schema::create('record_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('record_id')->constrained()->cascadeOnDelete();
            $table->string('keyword', 100)->index();
            $table->string('source', 16)->default('manual');
            $table->boolean('is_confirmed')->default(false);
            $table->timestamps();

            $table->unique(['record_id', 'keyword']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('record_keywords');
        Schema::dropIfExists('record_subjects');
    }
};
