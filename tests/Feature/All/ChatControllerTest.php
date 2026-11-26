<?php

namespace Tests\Feature\All;

use App\Enums\ChatMessageStatus;
use App\Jobs\AnswerChatQuestionJob;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Record;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * "Ask me" routes, shared by every signed-in role: asking (queued, one at a
 * time), and listing, reading and deleting one's own saved conversations.
 */
class ChatControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_first_question_starts_a_conversation_titled_after_it(): void
    {
        Queue::fake([AnswerChatQuestionJob::class]);
        $viewer = User::factory()->create();
        $record = Record::factory()->approved()->create();

        $response = $this->actingAs($viewer)
            ->postJson(route('chat.store'), ['question' => "  What is this\nvideo about?  ", 'record_id' => strtoupper($record->id)])
            ->assertAccepted()
            ->assertJsonPath('message.content', "What is this\nvideo about?")
            ->assertJsonPath('message.status', 'pending')
            ->assertJsonPath('conversation.title', 'What is this video about?');

        $question = ChatMessage::query()->sole();
        $this->assertSame($viewer->id, $question->user_id);
        $this->assertSame($record->id, $question->record_id);
        $this->assertSame($response->json('conversation.id'), $question->chat_conversation_id);
        $this->assertSame($viewer->id, $question->conversation->user_id);
        Queue::assertPushed(AnswerChatQuestionJob::class, fn (AnswerChatQuestionJob $job): bool => $job->question->is($question));
    }

    public function test_a_question_continues_the_askers_own_conversation(): void
    {
        Queue::fake([AnswerChatQuestionJob::class]);
        $user = User::factory()->create();
        $conversation = ChatConversation::factory()->for($user)->create(['title' => 'Budget']);

        $this->actingAs($user)
            ->postJson(route('chat.store'), ['question' => 'And who spoke next?', 'conversation_id' => $conversation->id])
            ->assertAccepted()
            ->assertJsonPath('conversation.id', $conversation->id)
            ->assertJsonPath('conversation.title', 'Budget');

        $this->assertSame($conversation->id, ChatMessage::query()->sole()->chat_conversation_id);
        $this->assertSame(1, ChatConversation::query()->count());
    }

    public function test_nobody_can_ask_in_someone_elses_conversation(): void
    {
        Queue::fake([AnswerChatQuestionJob::class]);
        $theirs = ChatConversation::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson(route('chat.store'), ['question' => 'What did they ask?', 'conversation_id' => $theirs->id])
            ->assertForbidden();

        $this->assertSame(0, ChatMessage::query()->count());
        Queue::assertNotPushed(AnswerChatQuestionJob::class);
    }

    public function test_guests_and_deactivated_accounts_cannot_ask(): void
    {
        $this->postJson(route('chat.store'), ['question' => 'Who spoke?'])->assertUnauthorized();

        $this->actingAs(User::factory()->inactive()->create())
            ->postJson(route('chat.store'), ['question' => 'Who spoke?'])
            ->assertForbidden();
    }

    public function test_an_empty_question_or_malformed_ids_are_refused(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('chat.store'), ['question' => '   ', 'record_id' => '../records', 'conversation_id' => 'abc'])
            ->assertJsonValidationErrors([
                'question' => 'The question field is required.',
                'record_id' => 'The record id field must be a valid ULID.',
                'conversation_id' => 'The conversation id field must be an integer.',
            ]);
    }

    public function test_a_second_question_waits_for_the_first_answer(): void
    {
        Queue::fake([AnswerChatQuestionJob::class]);
        $user = User::factory()->encoder()->create();
        Cache::add(AnswerChatQuestionJob::lockKey($user->id), true, 60);

        $this->actingAs($user)
            ->postJson(route('chat.store'), ['question' => 'Who spoke first?'])
            ->assertConflict();

        Queue::assertNotPushed(AnswerChatQuestionJob::class);
        $this->assertSame(0, ChatConversation::query()->count());
    }

    public function test_the_history_lists_only_the_askers_conversations_most_recent_first(): void
    {
        $user = User::factory()->create();
        $older = ChatConversation::factory()->for($user)->create(['updated_at' => now()->subDay()]);
        $newer = ChatConversation::factory()->for($user)->create(['updated_at' => now()]);
        ChatConversation::factory()->create();

        $this->actingAs($user)
            ->getJson(route('chat.index'))
            ->assertOk()
            ->assertJsonPath('conversations.*.id', [$newer->id, $older->id])
            ->assertJsonPath('next_page', null);
    }

    public function test_the_history_is_paged(): void
    {
        $user = User::factory()->create();
        ChatConversation::factory()->count(21)->for($user)->create();

        $this->actingAs($user)
            ->getJson(route('chat.index'))
            ->assertJsonCount(20, 'conversations')
            ->assertJsonPath('next_page', 2);

        $this->actingAs($user)
            ->getJson(route('chat.index', ['page' => 2]))
            ->assertJsonCount(1, 'conversations')
            ->assertJsonPath('next_page', null);
    }

    public function test_a_conversation_shows_its_messages_oldest_first(): void
    {
        $user = User::factory()->encoder()->create();
        $conversation = ChatConversation::factory()->for($user)->create();
        $question = ChatMessage::factory()->in($conversation)->create(['content' => 'Who spoke?']);
        $citation = ['record_id' => '01m81q428bt5dn87eat575754z', 'title' => 'Fair', 'start' => 12.0, 'end' => 20.0, 'url' => 'http://localhost/watch/x?t=12'];
        $answer = ChatMessage::factory()->answer()->in($conversation)->create(['citations' => [$citation], 'follow_ups' => ['Who spoke next?']]);
        ChatMessage::factory()->for($user)->create(['content' => 'Another conversation']);

        $this->actingAs($user)
            ->getJson(route('chat.show', $conversation))
            ->assertOk()
            ->assertJsonPath('conversation.id', $conversation->id)
            ->assertJsonPath('messages.*.id', [$question->id, $answer->id])
            ->assertJsonPath('messages.1.citations.0.title', 'Fair')
            ->assertJsonPath('messages.1.follow_ups', ['Who spoke next?'])
            ->assertJsonPath('has_more', false)
            ->assertJsonPath('pending', false);
    }

    public function test_nobody_reads_or_deletes_someone_elses_conversation(): void
    {
        $theirs = ChatConversation::factory()->create();
        ChatMessage::factory()->in($theirs)->create();
        $intruder = User::factory()->admin()->create();

        $this->actingAs($intruder)->getJson(route('chat.show', $theirs))->assertForbidden();
        $this->actingAs($intruder)->deleteJson(route('chat.destroy', $theirs))->assertForbidden();

        $this->assertModelExists($theirs);
    }

    public function test_older_messages_are_paged(): void
    {
        $conversation = ChatConversation::factory()->create();
        $messages = ChatMessage::factory()->count(31)->in($conversation)->create();

        $this->actingAs($conversation->user)
            ->getJson(route('chat.show', $conversation))
            ->assertJsonCount(30, 'messages')
            ->assertJsonPath('messages.0.id', $messages[1]->id)
            ->assertJsonPath('has_more', true);

        $this->actingAs($conversation->user)
            ->getJson(route('chat.show', ['conversation' => $conversation, 'before' => $messages[1]->id]))
            ->assertJsonPath('messages.*.id', [$messages[0]->id])
            ->assertJsonPath('has_more', false);
    }

    public function test_deleting_a_conversation_removes_only_it_and_its_messages(): void
    {
        $conversation = ChatConversation::factory()->create();
        ChatMessage::factory()->count(2)->in($conversation)->create();
        $kept = ChatMessage::factory()->for($conversation->user)->create();

        $this->actingAs($conversation->user)
            ->deleteJson(route('chat.destroy', $conversation))
            ->assertNoContent();

        $this->assertModelMissing($conversation);
        $this->assertSame([$kept->id], ChatMessage::query()->pluck('id')->all());
    }

    public function test_a_conversation_cannot_be_deleted_while_its_question_is_answered(): void
    {
        $conversation = ChatConversation::factory()->create();
        ChatMessage::factory()->pending()->in($conversation)->create();
        Cache::add(AnswerChatQuestionJob::lockKey($conversation->user_id), true, 60);

        $this->actingAs($conversation->user)
            ->deleteJson(route('chat.destroy', $conversation))
            ->assertConflict();

        $this->assertModelExists($conversation);
    }

    public function test_clearing_the_history_removes_only_the_askers_conversations(): void
    {
        $user = User::factory()->create();
        ChatMessage::factory()->count(2)->for($user)->create();
        $kept = ChatMessage::factory()->create();

        $this->actingAs($user)
            ->deleteJson(route('chat.clear'))
            ->assertNoContent();

        $this->assertSame([$kept->id], ChatMessage::query()->pluck('id')->all());
        $this->assertSame([$kept->chat_conversation_id], ChatConversation::query()->pluck('id')->all());
    }

    public function test_the_history_cannot_be_cleared_while_a_question_is_answered(): void
    {
        $user = User::factory()->create();
        ChatMessage::factory()->pending()->for($user)->create();
        Cache::add(AnswerChatQuestionJob::lockKey($user->id), true, 60);

        $this->actingAs($user)
            ->deleteJson(route('chat.clear'))
            ->assertConflict();

        $this->assertSame(ChatMessageStatus::Pending, ChatMessage::query()->sole()->status);
    }
}
