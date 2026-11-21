<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custom JSON export schemas (PROJECT_SPEC §8, Phase 2): a user's named list
 * of dot-paths to keep from the §10 document. One may be the user's default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_schemas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->jsonb('fields');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            // Listing a user's schemas (by name) and finding their default.
            $table->index(['user_id', 'name']);
            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_schemas');
    }
};
