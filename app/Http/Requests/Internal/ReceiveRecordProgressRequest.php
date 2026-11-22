<?php

namespace App\Http\Requests\Internal;

use App\Enums\PipelineStage;
use App\Enums\StageStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceiveRecordProgressRequest extends FormRequest
{
    /**
     * The VerifyInternalSignature middleware has already authenticated the caller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `done` is not accepted here: a stage is done only when its result arrives.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'stage' => ['required', Rule::in(array_map(fn (PipelineStage $stage): string => $stage->value, PipelineStage::tracked()))],
            'status' => ['required', Rule::in([StageStatus::Running->value, StageStatus::Failed->value, StageStatus::Skipped->value])],
            'progress' => ['required', 'integer', 'between:0,100'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
