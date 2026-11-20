<?php

namespace App\Models;

use App\Enums\AccessLevel;
use App\Enums\PermissionStatus;
use App\Enums\ReleaseConsent;
use Database\Factories\RecordRightsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Rights and access metadata for a record.
 *
 * @property int $id
 * @property string $record_id
 * @property string|null $copyright_holder
 * @property int|null $copyright_year
 * @property string|null $rights_statement
 * @property string|null $license
 * @property AccessLevel $access_level
 * @property string|null $usage_restrictions
 * @property PermissionStatus $permission_status
 * @property ReleaseConsent $release_consent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['copyright_holder', 'copyright_year', 'rights_statement', 'license', 'access_level', 'usage_restrictions', 'permission_status', 'release_consent'])]
class RecordRights extends Model
{
    /** @use HasFactory<RecordRightsFactory> */
    use HasFactory;

    protected $table = 'record_rights';

    /**
     * Defaults mirrored from the migration so new models see them before a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'access_level' => 'restricted',
        'permission_status' => 'pending',
        'release_consent' => 'not_available',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'copyright_year' => 'integer',
            'access_level' => AccessLevel::class,
            'permission_status' => PermissionStatus::class,
            'release_consent' => ReleaseConsent::class,
        ];
    }

    /**
     * @return BelongsTo<Record, $this>
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class);
    }

    /**
     * Whether the rights allow showing the record on the public site.
     */
    public function allowsPublicAccess(): bool
    {
        return $this->access_level === AccessLevel::Public
            && $this->permission_status === PermissionStatus::Approved;
    }
}
