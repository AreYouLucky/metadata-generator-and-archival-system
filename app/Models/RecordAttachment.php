<?php

namespace App\Models;

use App\Enums\AttachmentKind;
use Carbon\CarbonImmutable as Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A private file attached to a record (release form, contract, sidecar...).
 *
 * @property string $id
 * @property string $record_id
 * @property int|null $uploaded_by
 * @property AttachmentKind $kind
 * @property string $path
 * @property string $original_filename
 * @property string $mime_type
 * @property int $file_size
 * @property string $sha256_checksum
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['kind', 'original_filename', 'description'])]
#[Hidden(['path'])]
class RecordAttachment extends Model
{
    use HasUlids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => AttachmentKind::class,
            'file_size' => 'integer',
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
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
