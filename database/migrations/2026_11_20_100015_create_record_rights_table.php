<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rights and access metadata: who owns the record and who may use it.
 * Release forms themselves are stored as record_attachments.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('record_rights', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('record_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('copyright_holder')->nullable();
            $table->unsignedSmallInteger('copyright_year')->nullable();
            $table->text('rights_statement')->nullable();
            $table->string('license')->nullable();
            $table->string('license_url')->nullable();
            $table->string('access_level', 16)->default('restricted')->index();
            $table->text('usage_restrictions')->nullable();
            $table->jsonb('territories')->nullable();
            $table->timestamp('embargo_until')->nullable()->index();
            $table->string('permission_status', 16)->default('pending');
            $table->string('release_consent', 16)->default('not_available');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('record_rights');
    }
};
