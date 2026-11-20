<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('catalogue_records', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->date('date_shot')->nullable()->index();
            $table->string('date_shot_precision', 8)->nullable();
            $table->string('date_shot_source', 16)->nullable();
            $table->string('location_name')->nullable();
            $table->decimal('location_lat', 10, 7)->nullable();
            $table->decimal('location_lng', 10, 7)->nullable();
            $table->string('location_source', 16)->nullable();
            $table->string('series_title')->nullable()->index();
            $table->unsignedSmallInteger('season_number')->nullable();
            $table->unsignedSmallInteger('episode_number')->nullable();
            $table->string('episode_title')->nullable();
            $table->jsonb('keywords')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalogue_records');
    }
};
