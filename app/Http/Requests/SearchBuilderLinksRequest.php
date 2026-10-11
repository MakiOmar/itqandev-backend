<?php

namespace App\Http\Requests;

use App\Services\ContentTypes\ContentTypeRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchBuilderLinksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageSettings') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', Rule::in(app(ContentTypeRegistry::class)->keys())],
        ];
    }
}
