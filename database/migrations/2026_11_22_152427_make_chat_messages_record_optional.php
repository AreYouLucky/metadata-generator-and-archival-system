<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Ask me" floats on every page (owner's request): one conversation per
 * account across the archive. `record_id` is now only the page a question
 * was asked on (optional), and removing that record keeps the conversation.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropForeign(['record_id']);
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->ulid('record_id')->nullable()->change();
            $table->foreign('record_id')->references('id')->on('records')->nullOnDelete();
            $table->index(['user_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'id']);
            $table->dropForeign(['record_id']);
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->ulid('record_id')->nullable(false)->change();
            $table->foreign('record_id')->references('id')->on('records')->cascadeOnDelete();
        });
    }
};
