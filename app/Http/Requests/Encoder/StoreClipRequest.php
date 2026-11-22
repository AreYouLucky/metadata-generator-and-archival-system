<?php

namespace App\Http\Requests\Encoder;

use App\Models\Record;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Cut a clip `{start, end, title?}` (seconds) from the record's master. A
 * clip is a download, like an export, so whoever may view the record may
 * make one. It must fit inside the video and the length limits, and a
 * record holds at most `archive.clips.max_per_record` clips.
 */
class StoreClipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->record() instanceof Record && ($this->user()?->can('export', $this->record()) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('title'))) {
            $title = trim($this->input('title'));
            $this->merge(['title' => $title === '' ? null : $title]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start' => ['required', 'numeric', 'min:0'],
            'end' => ['required', 'numeric', 'gt:start'],
            'title' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $start = (float) $this->input('start');
            $end = (float) $this->input('end');
            $length = $end - $start;
            $min = (float) config('archive.clips.min_seconds');
            $max = (float) config('archive.clips.max_seconds');
            $duration = $this->record()?->masterFile()->first(['id', 'record_id', 'role'])?->technicalMetadata()->value('duration');

            if ($length < $min) {
                $validator->errors()->add('end', "A clip must be at least {$this->seconds($min)} long.");
            } elseif ($length > $max) {
                $validator->errors()->add('end', "A clip can be at most {$this->seconds($max)} long.");
            }

            if ($duration !== null && $end > (float) $duration + 0.5) {
                $validator->errors()->add('end', 'The clip ends after the video does.');
            }

            if ($this->record()?->clips()->count() >= (int) config('archive.clips.max_per_record')) {
                $validator->errors()->add('start', 'This record already has the most clips it can hold. Delete some first.');
            }
        }];
    }

    private function record(): ?Record
    {
        $record = $this->route('record');

        return $record instanceof Record ? $record : null;
    }

    /**
     * "1 second", "1.5 seconds", "10 minutes".
     */
    private function seconds(float $seconds): string
    {
        [$amount, $unit] = $seconds >= 60 && fmod($seconds, 60) === 0.0 ? [$seconds / 60, 'minute'] : [$seconds, 'second'];
        $number = rtrim(rtrim(number_format($amount, 1), '0'), '.');

        return $number.' '.($number === '1' ? $unit : "{$unit}s");
    }
}
