<?php

namespace App\Http\Requests\Encoder;

use App\Models\Record;
use App\Models\RecordSegment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSegmentRequest extends FormRequest
{
    /** Longest timing accepted, in seconds (24 hours). */
    private const MAX_SECONDS = 86400;

    public function authorize(): bool
    {
        $record = $this->route('record');

        return $record instanceof Record && ($this->user()?->can('update', $record) ?? false);
    }

    /**
     * Text, speaker, timing or any mix. A speaker must belong to the same
     * record; null clears it. Timing comes as a start and end pair.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Record $record */
        $record = $this->route('record');

        return [
            'text' => ['sometimes', 'required', 'string', 'max:5000'],
            'speaker_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('record_speakers', 'id')->where('record_id', $record->id),
            ],
            'start' => ['required_with:end', 'numeric', 'min:0', 'max:'.self::MAX_SECONDS],
            'end' => ['required_with:start', 'numeric', 'gt:start', 'max:'.self::MAX_SECONDS],
        ];
    }

    /**
     * Retimed lines may overlap their neighbours, but not pass them: a line's
     * start stays between the previous and the next line's starts, so the
     * transcript keeps its order.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $segment = $this->route('segment');

                if (! $segment instanceof RecordSegment || ! $this->filled('start') || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $start = (float) $this->input('start');
                $siblings = RecordSegment::query()->where('record_transcript_id', $segment->record_transcript_id);
                $previousStart = (clone $siblings)->where('position', '<', $segment->position)->orderByDesc('position')->value('start');
                $nextStart = (clone $siblings)->where('position', '>', $segment->position)->orderBy('position')->value('start');

                if (($previousStart !== null && $start < (float) $previousStart) || ($nextStart !== null && $start > (float) $nextStart)) {
                    $validator->errors()->add('start', 'A line cannot start before the previous line or after the next one.');
                }
            },
        ];
    }
}
