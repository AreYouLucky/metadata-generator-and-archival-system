<?php

namespace App\Http\Requests\Encoder;

use App\Models\Record;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /records/{record}/thumbnail: exactly one of a key frame of this
 * record, a time in the video ("use current frame"), or an image file.
 */
class SetThumbnailRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('record');

        return $record instanceof Record && ($this->user()?->can('update', $record) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Record $record */
        $record = $this->route('record');
        $duration = $record->masterFile()->first()?->technicalMetadata()->value('duration');

        return [
            'frame_id' => [
                'required_without_all:time_sec,image',
                'prohibits:time_sec,image',
                'integer',
                Rule::exists('record_frames', 'id')->where('record_id', $record->id),
            ],
            'time_sec' => [
                'required_without_all:frame_id,image',
                'prohibits:frame_id,image',
                'numeric',
                'min:0',
                ...($duration !== null ? ['max:'.(float) $duration] : []),
            ],
            'image' => [
                'required_without_all:frame_id,time_sec',
                'prohibits:frame_id,time_sec',
                ...StoreProjectRequest::thumbnailRules(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'frame_id.exists' => 'That frame does not belong to this record.',
            'time_sec.max' => 'That time is past the end of the video.',
            'image.max' => 'The thumbnail must be 5 MB or smaller.',
            'image.mimes' => 'Use a JPG, PNG or WebP image for the thumbnail.',
        ];
    }
}
