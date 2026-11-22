<?php

namespace App\Http\Requests\Encoder;

use App\Models\Record;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSpeakerRequest extends FormRequest
{
    /** Enough for a panel discussion; stops a runaway client filling the table. */
    public const MAX_SPEAKERS = 50;

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
        return [
            'display_name' => ['nullable', 'string', 'max:80'],
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

                if ($record->speakers()->count() >= self::MAX_SPEAKERS) {
                    $validator->errors()->add('display_name', 'A record can have at most '.self::MAX_SPEAKERS.' speakers.');
                }
            },
        ];
    }
}
