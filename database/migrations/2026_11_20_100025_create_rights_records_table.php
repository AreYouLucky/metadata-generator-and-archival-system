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
        Schema::create('rights_records', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('copyright_holder')->nullable();
            $table->unsignedSmallInteger('copyright_year')->nullable();
            $table->string('licence', 32)->default('all_rights_reserved');
            $table->text('licence_text')->nullable();
            $table->string('licence_url')->nullable();
            $table->text('restrictions')->nullable();
            $table->jsonb('territories')->nullable();
            $table->timestamp('embargo_until')->nullable()->index();
            $table->jsonb('credits')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rights_records');
    }
};
