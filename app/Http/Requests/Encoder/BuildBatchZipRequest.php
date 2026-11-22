<?php

namespace App\Http\Requests\Encoder;

use App\Models\Batch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Build (or rebuild) a batch's ZIP of exports. Thumbnails are optional, as
 * they make the archive larger.
 */
class BuildBatchZipRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('batch');

        return $batch instanceof Batch && ($this->user()?->can('download', $batch) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'thumbnails' => ['sometimes', 'boolean'],
        ];
    }
}
