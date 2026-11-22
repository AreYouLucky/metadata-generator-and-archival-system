<?php

namespace App\Http\Requests\User;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Public catalogue filters (PROJECT_SPEC §8 `GET /browse`). Every value is
 * checked here; the sort is a whitelist. Also used by the project and
 * series pages for `page` and `series`.
 */
class BrowseCatalogueRequest extends FormRequest
{
    public const array SORTS = ['date_desc', 'date_asc', 'title'];

    /**
     * Login (when the catalogue requires it) is enforced by the route
     * middleware; the catalogue itself is open to every visitor.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project' => ['nullable', 'ulid'],
            'series' => ['nullable', 'ulid'],
            'genre' => ['nullable', 'string', 'max:100', 'alpha_dash:ascii'],
            'type' => ['nullable', 'string', 'max:100', 'alpha_dash:ascii'],
            'agency' => ['nullable', 'integer', 'min:1'],
            'region' => ['nullable', 'integer', 'min:1'],
            'year' => ['nullable', 'integer', 'between:1900,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(self::SORTS)],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }

    /**
     * @return array{project: string|null, series: string|null, genre: string|null, type: string|null, agency: int|null, region: int|null, year: int|null, month: int|null, q: string|null, sort: string}
     */
    public function filters(): array
    {
        $int = fn (string $key): ?int => $this->validated($key) === null ? null : (int) $this->validated($key);
        $year = $int('year');
        $q = trim((string) $this->validated('q'));

        return [
            'project' => $this->validated('project'),
            'series' => $this->validated('series'),
            'genre' => $this->validated('genre'),
            'type' => $this->validated('type'),
            'agency' => $int('agency'),
            'region' => $int('region'),
            'year' => $year,
            // A month means nothing without its year.
            'month' => $year === null ? null : $int('month'),
            'q' => $q === '' ? null : $q,
            'sort' => $this->validated('sort') ?? 'date_desc',
        ];
    }

    public function page(): int
    {
        return (int) ($this->validated('page') ?? 1);
    }
}
