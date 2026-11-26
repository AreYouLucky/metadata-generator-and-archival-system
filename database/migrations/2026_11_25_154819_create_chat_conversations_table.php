<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * "Ask me" keeps a history of conversations (owner's request) instead of one
 * endless chat per account. Each message belongs to a conversation; the
 * existing chats become each account's first saved conversation. Answers may
 * also suggest a few follow-up questions (`follow_ups`).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->timestamps();

            $table->index(['user_id', 'updated_at', 'id']);
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->foreignId('chat_conversation_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->json('follow_ups')->nullable()->after('tools');

            $table->index(['chat_conversation_id', 'id']);
        });

        $this->moveExistingChats();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex(['chat_conversation_id', 'id']);
            $table->dropConstrainedForeignId('chat_conversation_id');
            $table->dropColumn('follow_ups');
        });

        Schema::dropIfExists('chat_conversations');
    }

    /**
     * One conversation per account that already chatted, titled after its
     * first question.
     */
    private function moveExistingChats(): void
    {
        DB::table('chat_messages')
            ->select('user_id')
            ->selectRaw('min(created_at) as first_at, max(created_at) as last_at')
            ->whereNull('chat_conversation_id')
            ->groupBy('user_id')
            ->orderBy('user_id')
            ->chunk(200, function ($accounts): void {
                foreach ($accounts as $account) {
                    $first = DB::table('chat_messages')
                        ->where('user_id', $account->user_id)
                        ->where('role', 'user')
                        ->orderBy('id')
                        ->value('content');

                    $conversationId = DB::table('chat_conversations')->insertGetId([
                        'user_id' => $account->user_id,
                        'title' => Str::limit(trim((string) ($first ?? 'Earlier chat')), 117) ?: 'Earlier chat',
                        'created_at' => $account->first_at ?? now(),
                        'updated_at' => $account->last_at ?? now(),
                    ]);

                    DB::table('chat_messages')
                        ->where('user_id', $account->user_id)
                        ->whereNull('chat_conversation_id')
                        ->update(['chat_conversation_id' => $conversationId]);
                }
            });
    }
};
