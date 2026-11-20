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
        Schema::create('provenance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('source_carrier', 16)->nullable();
            $table->string('carrier_label')->nullable();
            $table->string('carrier_id')->nullable();
            $table->date('transfer_date')->nullable();
            $table->string('transfer_facility')->nullable();
            $table->string('transfer_operator')->nullable();
            $table->string('transfer_deck')->nullable();
            $table->text('transfer_settings')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provenance_records');
    }
};
