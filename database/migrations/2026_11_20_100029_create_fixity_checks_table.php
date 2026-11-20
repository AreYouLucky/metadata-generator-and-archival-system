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
        Schema::create('fixity_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('media_file_id')->constrained()->cascadeOnDelete();
            $table->string('algorithm', 16);
            $table->string('expected', 64);
            $table->string('actual', 64)->nullable();
            $table->boolean('matched')->nullable();
            $table->string('triggered_by', 16);
            $table->timestamp('checked_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['media_file_id', 'checked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixity_checks');
    }
};
