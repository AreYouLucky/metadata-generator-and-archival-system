<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chat with the video (Phase 7). Each person has their own conversation per
 * record. Questions are `pending` until answered; answers are `answered`
 * (backed by the record's data), `not_found` (the data doesn't say) or
 * `failed`. `tools` lists the function tools the answer used.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);
            $table->text('content');
            $table->string('status', 16);
            $table->json('citations')->nullable();
            $table->json('tools')->nullable();
            $table->timestamps();

            $table->index(['record_id', 'user_id', 'id']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
