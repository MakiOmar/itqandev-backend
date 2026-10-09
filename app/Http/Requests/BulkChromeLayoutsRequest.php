<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesChromeLayoutKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Bulk delete of Theme Builder layouts; ids must belong to the route's layout kind.
 */
class BulkChromeLayoutsRequest extends FormRequest
{
    use ResolvesChromeLayoutKind;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => [
                'integer',
                'distinct',
                Rule::exists('chrome_layouts', 'id')->where('kind', $this->layoutKind()),
            ],
        ];
    }
}
