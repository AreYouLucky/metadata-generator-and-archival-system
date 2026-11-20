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
        Schema::create('scenes', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 8)->default('shot');
            $table->decimal('start', 12, 3);
            $table->decimal('end', 12, 3);
            $table->string('tc_in', 16)->nullable();
            $table->string('tc_out', 16)->nullable();
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scenes');
    }
};
