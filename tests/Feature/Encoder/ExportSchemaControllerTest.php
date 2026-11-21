<?php

namespace Tests\Feature\Encoder;

use App\Enums\Preset;
use App\Models\ExportSchema;
use App\Models\Record;
use App\Models\RecordSegment;
use App\Models\RecordTranscript;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExportSchemaControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_settings_page_lists_only_the_users_own_schemas_and_the_field_groups(): void
    {
        $user = User::factory()->encoder()->create();
        ExportSchema::factory()->for($user)->create(['name' => 'Mine']);
        ExportSchema::factory()->create(['name' => 'Someone else']);

        $this->actingAs($user)
            ->get(route('export-schemas.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('encoder/settings/export-schemas-page/export-schemas')
                ->has('schemas', 1)
                ->where('schemas.0.name', 'Mine')
                ->where('fieldGroups.0.group', 'Record'));
    }

    public function test_viewers_cannot_open_the_settings_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('export-schemas.index'))
            ->assertForbidden();
    }

    public function test_a_new_default_schema_takes_over_from_the_previous_default(): void
    {
        $user = User::factory()->encoder()->create();
        $previous = ExportSchema::factory()->for($user)->default()->create();

        $this->actingAs($user)
            ->postJson(route('export-schemas.store'), [
                'name' => 'YouTube upload',
                'fields' => ['record.title', 'ai.description', 'ai.tags'],
                'is_default' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('schema.name', 'YouTube upload');

        $this->assertFalse($previous->refresh()->is_default);
        $this->assertSame(['record.title', 'ai.description', 'ai.tags'], $user->exportSchemas()->where('is_default', true)->sole()->fields);
    }

    public function test_unknown_fields_and_duplicate_names_are_refused(): void
    {
        $user = User::factory()->encoder()->create();
        ExportSchema::factory()->for($user)->create(['name' => 'Taken']);

        $this->actingAs($user)
            ->postJson(route('export-schemas.store'), ['name' => 'Taken', 'fields' => ['record.title', 'record.secret'], 'is_default' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'You already have a schema with that name.', 'fields.1']);
        $this->actingAs($user)
            ->postJson(route('export-schemas.store'), ['name' => 'Empty', 'fields' => [], 'is_default' => false])
            ->assertJsonValidationErrors(['fields' => 'Pick at least one field.']);
    }

    public function test_another_users_schema_cannot_be_changed_deleted_or_used(): void
    {
        $schema = ExportSchema::factory()->create();
        $intruder = User::factory()->encoder()->create();
        $record = Record::factory()->for($intruder, 'creator')->create();

        $this->actingAs($intruder)
            ->patchJson(route('export-schemas.update', $schema), ['name' => 'Mine now', 'fields' => ['record'], 'is_default' => false])
            ->assertForbidden();
        $this->actingAs($intruder)
            ->deleteJson(route('export-schemas.destroy', $schema))
            ->assertForbidden();
        $this->actingAs($intruder)
            ->getJson(route('records.export', [$record, 'format' => 'json', 'schema' => $schema->id]))
            ->assertJsonValidationErrors(['schema']);
        $this->assertModelExists($schema);
    }

    public function test_the_owner_can_rename_and_delete_a_schema(): void
    {
        $schema = ExportSchema::factory()->create(['name' => 'Old']);

        $this->actingAs($schema->user)
            ->patchJson(route('export-schemas.update', $schema), ['name' => 'New', 'fields' => ['transcript'], 'is_default' => false])
            ->assertOk();
        $this->assertSame(['New', ['transcript']], [$schema->refresh()->name, $schema->fields]);

        $this->actingAs($schema->user)->deleteJson(route('export-schemas.destroy', $schema))->assertOk();
        $this->assertModelMissing($schema);
    }

    public function test_a_json_export_with_a_schema_keeps_only_its_fields(): void
    {
        $record = Record::factory()->create(['title' => 'Fair opening']);
        $transcript = RecordTranscript::factory()->for($record)->create();
        RecordSegment::factory()->for($transcript, 'transcript')->create(['position' => 1, 'start' => 0, 'end' => 2, 'text' => 'Welcome.']);
        $record->metadata()->create(['preset' => Preset::Generic, 'model' => 'gpt-6.2-sol', 'is_active' => true, 'payload' => ['description' => 'An opening.', 'tags' => ['fair']]]);
        $schema = ExportSchema::factory()->for($record->creator)->create([
            'name' => 'Web',
            'fields' => ['transcript', 'ai.tags', 'record.title', 'ai.description'],
        ]);

        $json = json_decode((string) $this->actingAs($record->creator)
            ->get(route('records.export', [$record, 'format' => 'json', 'schema' => $schema->id]))
            ->assertOk()
            ->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(['schemaVersion', 'generatedAt', 'record', 'transcript', 'ai'], array_keys($json));
        $this->assertSame(['title' => 'Fair opening'], $json['record']);
        $this->assertSame(['description' => 'An opening.', 'tags' => ['fair']], $json['ai']);
        $this->assertSame('Welcome.', $json['transcript'][0]['text']);
        $this->assertDatabaseHas('video_audit_logs', ['record_id' => $record->id, 'remarks' => 'Format: json, schema: Web']);
    }
}
