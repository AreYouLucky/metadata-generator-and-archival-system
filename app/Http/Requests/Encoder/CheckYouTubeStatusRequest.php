<?php

namespace App\Http\Requests\Encoder;

use App\Models\Record;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ask YouTube again for a publication's status: whoever may open the record
 * (the {publication} route value is scoped to the record).
 */
class CheckYouTubeStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('record');

        return $record instanceof Record && ($this->user()?->can('view', $record) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
