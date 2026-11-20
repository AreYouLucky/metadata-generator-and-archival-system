<?php

namespace App\Models;

use App\Enums\SourceCarrier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Where a video came from: source carrier and transfer details.
 *
 * @property int $id
 * @property string $project_id
 * @property SourceCarrier|null $source_carrier
 * @property string|null $carrier_label
 * @property string|null $carrier_id
 * @property Carbon|null $transfer_date
 * @property string|null $transfer_facility
 * @property string|null $transfer_operator
 * @property string|null $transfer_deck
 * @property string|null $transfer_settings
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['source_carrier', 'carrier_label', 'carrier_id', 'transfer_date', 'transfer_facility', 'transfer_operator', 'transfer_deck', 'transfer_settings', 'notes'])]
class ProvenanceRecord extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_carrier' => SourceCarrier::class,
            'transfer_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
