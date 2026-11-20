<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A project is an event or programme (e.g. a conference) that groups records.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title');
            $table->date('date')->nullable()->index();
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('prov_id')->nullable()->constrained('provinces')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->foreignId('brgy_id')->nullable()->constrained('barangays')->nullOnDelete();
            $table->foreignId('agency_id')->nullable()->constrained('production_agencies')->nullOnDelete();
            $table->string('thumbnail_path')->nullable();
            $table->timestamps();

            $table->index('agency_id');
            $table->index('region_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
