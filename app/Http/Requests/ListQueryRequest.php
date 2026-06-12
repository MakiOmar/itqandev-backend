<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort_by' => ['nullable', 'string', 'max:64'],
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function perPage(int $default = 20): int
    {
        return max(1, min((int) ($this->validated('per_page') ?? $default), 100));
    }

    public function sortBy(string $default, array $allowed): string
    {
        $sort = (string) ($this->validated('sort_by') ?? $default);

        return in_array($sort, $allowed, true) ? $sort : $default;
    }

    public function sortOrder(string $default = 'desc'): string
    {
        $order = strtolower((string) ($this->validated('sort_order') ?? $default));

        return $order === 'asc' ? 'asc' : 'desc';
    }
}
