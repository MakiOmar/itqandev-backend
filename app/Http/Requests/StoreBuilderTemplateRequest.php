<?php

namespace App\Http\Requests;

use App\Models\BuilderTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBuilderTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', BuilderTemplate::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', 'string', Rule::in(BuilderTemplate::KINDS)],
            'document' => ['required', 'array'],
        ];
    }
}
