<?php

namespace App\Http\Requests\User;

use App\Enums\TaskStatus;
use App\Models\Record;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Captions for the public player, in the original language or a finished
 * translation. A record that may not be distributed answers 404, so the
 * public can't learn that it exists.
 */
class ShowCatalogueCaptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('record');

        return $record instanceof Record && Gate::forUser(null)->allows('distribute', $record);
    }

    protected function failedAuthorization(): void
    {
        throw new NotFoundHttpException;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['language' => $this->route('language')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Record $record */
        $record = $this->route('record');

        return [
            'language' => [
                'required',
                'string',
                'max:16',
                Rule::exists('record_transcripts', 'language')
                    ->where('record_id', $record->id)
                    ->where('status', TaskStatus::Done->value),
            ],
        ];
    }
}
