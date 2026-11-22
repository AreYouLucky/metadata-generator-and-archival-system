<?php

namespace Tests\Feature\All;

use App\Enums\ChatMessageStatus;
use App\Jobs\AnswerChatQuestionJob;
use App\Models\ChatMessage;
use App\Models\Record;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * "Ask me" routes, shared by every signed-in role: asking (queued, one at a
 * time), reading and clearing one's own conversation.
 */
class ChatControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_signed_in_role_can_ask_with_the_page_as_a_hint(): void
    {
        Queue::fake([AnswerChatQuestionJob::class]);
        $viewer = User::factory()->create();
        $record = Record::factory()->approved()->create();

        $this->actingAs($viewer)
            ->postJson(route('chat.store'), ['question' => '  What is this video about?  ', 'record_id' => strtoupper($record->id)])
            ->assertAccepted()
            ->assertJsonPath('message.content', 'What is this video about?')
            ->assertJsonPath('message.status', 'pending');

        $question = ChatMessage::query()->sole();
        $this->assertSame($viewer->id, $question->user_id);
        $this->assertSame($record->id, $question->record_id);
        Queue::assertPushed(AnswerChatQuestionJob::class, fn (AnswerChatQuestionJob $job): bool => $job->question->is($question));
    }

    public function test_guests_and_deactivated_accounts_cannot_ask(): void
    {
        $this->postJson(route('chat.store'), ['question' => 'Who spoke?'])->assertUnauthorized();

        $this->actingAs(User::factory()->inactive()->create())
            ->postJson(route('chat.store'), ['question' => 'Who spoke?'])
            ->assertForbidden();
    }

    public function test_an_empty_question_or_a_malformed_page_id_is_refused(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('chat.store'), ['question' => '   ', 'record_id' => '../records'])
            ->assertJsonValidationErrors([
                'question' => 'The question field is required.',
                'record_id' => 'The record id field must be a valid ULID.',
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
    }

    public function test_each_person_reads_only_their_own_conversation_oldest_first(): void
    {
        $user = User::factory()->encoder()->create();
        $question = ChatMessage::factory()->for($user)->create(['content' => 'Who spoke?']);
        $citation = ['record_id' => '01m81q428bt5dn87eat575754z', 'title' => 'Fair', 'start' => 12.0, 'end' => 20.0, 'url' => 'http://localhost/watch/x?t=12'];
        $answer = ChatMessage::factory()->answer()->for($user)->create(['citations' => [$citation]]);
        ChatMessage::factory()->create(['content' => 'Someone else']);

        $this->actingAs($user)
            ->getJson(route('chat.index'))
            ->assertOk()
            ->assertJsonPath('messages.*.id', [$question->id, $answer->id])
            ->assertJsonPath('messages.1.citations.0.title', 'Fair')
            ->assertJsonPath('has_more', false)
            ->assertJsonPath('pending', false);
    }

    public function test_older_messages_are_paged(): void
    {
        $user = User::factory()->create();
        $messages = ChatMessage::factory()->count(31)->for($user)->create();

        $this->actingAs($user)
            ->getJson(route('chat.index'))
            ->assertJsonCount(30, 'messages')
            ->assertJsonPath('messages.0.id', $messages[1]->id)
            ->assertJsonPath('has_more', true);

        $this->actingAs($user)
            ->getJson(route('chat.index', ['before' => $messages[1]->id]))
            ->assertJsonPath('messages.*.id', [$messages[0]->id])
            ->assertJsonPath('has_more', false);
    }

    public function test_clearing_removes_only_the_askers_messages(): void
    {
        $user = User::factory()->create();
        ChatMessage::factory()->count(2)->for($user)->create();
        $kept = ChatMessage::factory()->create();

        $this->actingAs($user)
            ->deleteJson(route('chat.destroy'))
            ->assertNoContent();

        $this->assertSame([$kept->id], ChatMessage::query()->pluck('id')->all());
    }

    public function test_the_chat_cannot_be_cleared_while_a_question_is_answered(): void
    {
        $user = User::factory()->create();
        ChatMessage::factory()->pending()->for($user)->create();
        Cache::add(AnswerChatQuestionJob::lockKey($user->id), true, 60);

        $this->actingAs($user)
            ->deleteJson(route('chat.destroy'))
            ->assertConflict();

        $this->assertSame(ChatMessageStatus::Pending, ChatMessage::query()->sole()->status);
    }
}
