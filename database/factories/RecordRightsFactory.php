<?php

namespace Database\Factories;

use App\Enums\AccessLevel;
use App\Enums\PermissionStatus;
use App\Enums\ReleaseConsent;
use App\Models\Record;
use App\Models\RecordRights;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecordRights>
 */
class RecordRightsFactory extends Factory
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
            'copyright_holder' => 'DOST',
            'copyright_year' => (int) fake()->year(),
            'rights_statement' => 'Government-produced audiovisual material',
            'license' => 'Agency-defined license',
            'access_level' => AccessLevel::Restricted,
            'usage_restrictions' => 'Subject to agency approval',
            'permission_status' => PermissionStatus::Pending,
            'release_consent' => ReleaseConsent::NotAvailable,
        ];
    }

    /**
     * Public access with permission granted.
     */
    public function publicAccess(): static
    {
        return $this->state(fn (array $attributes) => [
            'access_level' => AccessLevel::Public,
            'permission_status' => PermissionStatus::Approved,
        ]);
    }
}
