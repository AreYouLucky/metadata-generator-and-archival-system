<?php

namespace Tests\Feature\Encoder;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_chunks_are_appended_in_order_and_the_part_file_grows(): void
    {
        Storage::fake('media');
        $encoder = User::factory()->encoder()->create();

        $id = $this->actingAs($encoder)
            ->postJson(route('records.uploads.store'), ['filename' => 'opening.mp4', 'size' => 10])
            ->assertCreated()
            ->assertJsonPath('received', 0)
            ->json('id');

        $this->postJson(route('records.uploads.chunk', $id), ['offset' => 0, 'chunk' => UploadedFile::fake()->createWithContent('chunk', '12345')])
            ->assertOk()
            ->assertJsonPath('received', 5);

        $this->postJson(route('records.uploads.chunk', $id), ['offset' => 5, 'chunk' => UploadedFile::fake()->createWithContent('chunk', '67890')])
            ->assertOk()
            ->assertJsonPath('received', 10);

        $this->assertSame('1234567890', Storage::disk('media')->get("uploads/{$id}.part"));
    }

    public function test_a_chunk_at_the_wrong_offset_is_rejected_with_409_so_the_client_can_resume(): void
    {
        Storage::fake('media');
        $encoder = User::factory()->encoder()->create();
        $id = $this->actingAs($encoder)->postJson(route('records.uploads.store'), ['filename' => 'opening.mp4', 'size' => 10])->json('id');

        $this->postJson(route('records.uploads.chunk', $id), ['offset' => 5, 'chunk' => UploadedFile::fake()->createWithContent('chunk', '67890')])
            ->assertConflict();

        $this->getJson(route('records.uploads.show', $id))->assertOk()->assertJsonPath('received', 0);
    }

    public function test_another_users_upload_is_reported_as_missing(): void
    {
        Storage::fake('media');
        $id = $this->actingAs(User::factory()->encoder()->create())
            ->postJson(route('records.uploads.store'), ['filename' => 'opening.mp4', 'size' => 10])
            ->json('id');

        $this->actingAs(User::factory()->encoder()->create())
            ->postJson(route('records.uploads.chunk', $id), ['offset' => 0, 'chunk' => UploadedFile::fake()->createWithContent('chunk', '12345')])
            ->assertNotFound();
    }

    public function test_a_file_that_is_not_a_video_or_too_large_is_refused_before_uploading(): void
    {
        $encoder = User::factory()->encoder()->create();
        config(['archive.max_upload_mb' => 1]);

        $this->actingAs($encoder)
            ->postJson(route('records.uploads.store'), ['filename' => 'notes.pdf', 'size' => 2 * 1024 * 1024])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'filename' => 'Choose a video file',
                'size' => 'The file is larger than the 1 MB limit.',
            ]);
    }

    public function test_viewers_cannot_upload(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('records.uploads.store'), ['filename' => 'opening.mp4', 'size' => 10])
            ->assertForbidden();
    }
}
