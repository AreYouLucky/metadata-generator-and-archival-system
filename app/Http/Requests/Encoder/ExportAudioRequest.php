<?php

namespace App\Http\Requests\Encoder;

use App\Models\Record;
use App\Services\Records\AudioExports;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * POST /records/{record}/audio-export: make the master's audio downloadable
 * as MP3 or WAV. Needs a probed master with an audio track.
 */
class ExportAudioRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('record');

        return $record instanceof Record && ($this->user()?->can('export', $record) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'format' => ['required', Rule::in(array_keys(AudioExports::FORMATS))],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Record $record */
                $record = $this->route('record');
                $technical = $record->masterFile()->first()?->technicalMetadata()->first(['id', 'audio_codec']);

                if ($technical === null) {
                    $validator->errors()->add('format', 'The video has not been analysed yet. Try again once processing has started.');
                } elseif ($technical->audio_codec === null) {
                    $validator->errors()->add('format', 'The video has no audio track.');
                }
            },
        ];
    }

    public function audioFormat(): string
    {
        return (string) $this->validated('format');
    }
}
