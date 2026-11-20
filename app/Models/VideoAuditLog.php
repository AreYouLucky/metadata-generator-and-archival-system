<?php

namespace App\Models;

use App\Enums\AuditAction;
use App\Models\Concerns\AppendOnly;
use Database\Factories\VideoAuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An entry in a record's append-only audit log. Rows are never updated or deleted.
 *
 * @property int $id
 * @property string $record_id
 * @property AuditAction $action
 * @property int|null $performed_by
 * @property Carbon $performed_at
 * @property string|null $remarks
 */
#[Fillable(['record_id', 'action', 'performed_by', 'performed_at', 'remarks'])]
class VideoAuditLog extends Model
{
    /** @use HasFactory<VideoAuditLogFactory> */
    use AppendOnly, HasFactory;

    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'performed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
