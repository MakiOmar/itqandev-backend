<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesChromeLayoutKind;
use App\Models\ChromeLayout;
use App\Services\Appearance\ChromeLayoutTransferService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Import envelope produced by `GET /api/appearance/{kind}/export`.
 */
class ImportChromeLayoutsRequest extends FormRequest
{
    use ResolvesChromeLayoutKind;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'format' => ['required', 'string', Rule::in([ChromeLayoutTransferService::FORMAT])],
            'version' => ['sometimes', 'integer', 'min:1', 'max:'.ChromeLayoutTransferService::VERSION],
            'kind' => ['required', 'string', Rule::in([$this->layoutKind()])],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.name' => ['required', 'string', 'max:120'],
            'items.*.slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'items.*.status' => ['sometimes', 'string', Rule::in([ChromeLayout::STATUS_DRAFT, ChromeLayout::STATUS_PUBLISHED])],
            'items.*.document' => ['required', 'array'],
            'items.*.document.sections' => ['required', 'array'],
            'items.*.document.overlay' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'format.in' => 'This file is not a Theme Builder layouts export.',
            'kind.in' => 'This file contains a different layout type.',
        ];
    }
}
