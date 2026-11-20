<?php

namespace Database\Factories;

use App\Enums\AuditAction;
use App\Models\Record;
use App\Models\VideoAuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VideoAuditLog>
 */
class VideoAuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'record_id' => Record::factory(),
            'action' => AuditAction::Uploaded,
            'performed_by' => null,
            'performed_at' => now(),
            'remarks' => null,
        ];
    }
}
