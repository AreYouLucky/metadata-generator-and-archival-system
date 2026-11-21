<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Translations run in the background (Phase 2): keep how far one has got and,
 * when it failed, why, so both survive a page reload.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('record_transcripts', function (Blueprint $table) {
            $table->unsignedTinyInteger('progress')->default(0)->after('status');
            $table->string('error', 1000)->nullable()->after('progress');
        });
    }

    public function down(): void
    {
        Schema::table('record_transcripts', function (Blueprint $table) {
            $table->dropColumn(['progress', 'error']);
        });
    }
};
