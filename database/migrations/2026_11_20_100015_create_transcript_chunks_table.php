<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The embedding column and its HNSW index need PostgreSQL with pgvector.
 * On other drivers (the SQLite test database) the table is created without them.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $usesPgvector = Schema::getConnection()->getDriverName() === 'pgsql';

        if ($usesPgvector) {
            Schema::ensureVectorExtensionExists();
        }

        Schema::create('transcript_chunks', function (Blueprint $table) use ($usesPgvector) {
            $table->id();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->decimal('start', 12, 3);
            $table->decimal('end', 12, 3);
            $table->text('text');

            if ($usesPgvector) {
                $table->vector('embedding', (int) config('archive.embedding_dimensions'))->nullable();
                $table->vectorIndex('embedding');
            }

            $table->timestamps();

            $table->index(['project_id', 'start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transcript_chunks');
    }
};
