<?php

namespace App\Http\Requests\Encoder;

use App\Models\Record;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Translate the transcript into one of the configured languages (costs AI
 * tokens, so it needs the same right as a regeneration).
 */
class StoreTranslationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->record() !== null && ($this->user()?->can('generate', $this->record()) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $original = $this->record()?->originalTranscript()->value('language');

        return [
            'language' => [
                'required',
                'string',
                Rule::in(array_keys(self::languages())),
                Rule::notIn(array_filter([$original])),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'language.not_in' => 'The transcript is already in that language.',
        ];
    }

    /**
     * Code => English name, from config/archive.php.
     *
     * @return array<string, string>
     */
    public static function languages(): array
    {
        /** @var array<string, string> */
        return config('archive.translation_languages', []);
    }

    private function record(): ?Record
    {
        $record = $this->route('record');

        return $record instanceof Record ? $record : null;
    }
}
