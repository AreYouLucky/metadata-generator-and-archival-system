<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Owner's choice (Phase 4): records are public by default. New rights
     * start as public access with permission approved; existing rows keep
     * what an encoder chose.
     */
    public function up(): void
    {
        Schema::table('record_rights', function (Blueprint $table) {
            $table->string('access_level', 16)->default('public')->change();
            $table->string('permission_status', 16)->default('approved')->change();
        });
    }

    public function down(): void
    {
        Schema::table('record_rights', function (Blueprint $table) {
            $table->string('access_level', 16)->default('restricted')->change();
            $table->string('permission_status', 16)->default('pending')->change();
        });
    }
};
