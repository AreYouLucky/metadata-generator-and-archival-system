<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every time an "Ask me" answer mentions is clickable (owner's request). A
 * time that falls on no citation opens the answer's default record: the
 * page the question was asked on, or the only record the tools returned.
 * `link` keeps that record and where this account may watch it, chosen
 * when the answer was written.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->json('link')->nullable()->after('citations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn('link');
        });
    }
};
