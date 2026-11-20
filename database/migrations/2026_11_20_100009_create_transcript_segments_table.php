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
        $supportsFullText = Schema::getConnection()->getDriverName() === 'pgsql';

        Schema::create('transcript_segments', function (Blueprint $table) use ($supportsFullText) {
            $table->id();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->decimal('start', 12, 3);
            $table->decimal('end', 12, 3);
            $table->text('text');
            $table->text('original_text');
            $table->foreignId('speaker_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_edited')->default(false);
            $table->timestamps();

            $table->index(['project_id', 'start']);
            $table->index(['project_id', 'position']);

            if ($supportsFullText) {
                $table->fullText('text');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transcript_segments');
    }
};
