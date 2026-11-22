<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * YouTube (Phase 7): one connected channel per user, from Google OAuth. The
 * tokens are encrypted at rest (model casts) and never sent to the browser.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('youtube_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('channel_id', 64);
            $table->string('channel_title', 200);
            $table->string('channel_thumbnail_url', 2048)->nullable();
            $table->text('access_token');
            $table->text('refresh_token');
            $table->timestamp('token_expires_at')->nullable();
            $table->text('scopes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('youtube_accounts');
    }
};
