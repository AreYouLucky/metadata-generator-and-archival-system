<?php

namespace App\Http\Requests\Admin;

use App\Models\Genre;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rename a genre or change its position in the list.
 */
class UpdateGenreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $genre = $this->route('genre');

        return $genre instanceof Genre && ($this->user()?->can('update', $genre) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Genre $genre */
        $genre = $this->route('genre');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('genres', 'name')->ignore($genre->id)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['name.unique' => 'There is already a genre with this name.'];
    }
}
