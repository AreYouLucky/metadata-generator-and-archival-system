<?php

namespace Database\Factories;

use App\Enums\AgentType;
use App\Enums\EventOutcome;
use App\Enums\PreservationEventType;
use App\Models\PreservationEvent;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PreservationEvent>
 */
class PreservationEventFactory extends Factory
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
            'type' => PreservationEventType::Ingest,
            'outcome' => EventOutcome::Success,
            'detail' => fake()->sentence(),
            'agent_type' => AgentType::System,
            'agent' => 'system',
            'occurred_at' => now(),
        ];
    }
}
