<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public API webhooks (Phase 7): a record created through the API may name a
 * `webhook_url` that Laravel POSTs to once processing finishes. Each user has
 * one signing secret (encrypted) so receivers can verify the call came from
 * the archive.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->string('webhook_url', 2048)->nullable()->after('source_url');
            $table->timestamp('webhook_sent_at')->nullable()->after('webhook_url');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->text('webhook_secret')->nullable()->after('daily_processing_minutes_limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->dropColumn(['webhook_url', 'webhook_sent_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('webhook_secret');
        });
    }
};
