<?php

namespace App\Http\Requests;

use App\Models\AppMedia;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `GET /v1/media/lookup?ids=1,2,3` — resolve many media ids to URLs in one call (builder previews).
 * Unknown ids are simply absent from the response, so there is no per-id `exists` rule.
 */
class LookupMediaRequest extends FormRequest
{
    public const MAX_IDS = 200;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', AppMedia::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $ids = $this->query('ids');
        if (is_string($ids)) {
            $this->merge(['ids' => array_values(array_filter(array_map('trim', explode(',', $ids)), fn ($v) => $v !== ''))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_IDS],
            'ids.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_values(array_unique(array_map('intval', $this->validated('ids'))));
    }
}
