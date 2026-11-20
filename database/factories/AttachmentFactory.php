<?php

namespace Database\Factories;

use App\Enums\AttachmentKind;
use App\Models\Attachment;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'kind' => AttachmentKind::ReleaseForm,
            'path' => 'attachments/'.Str::ulid().'.pdf',
            'original_filename' => 'release-form.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(10_000, 2_000_000),
            'sha256' => fake()->sha256(),
        ];
    }
}
