<?php

namespace App\Http\Requests;

use App\Models\BuilderTemplate;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBuilderTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $template = BuilderTemplate::query()->find((int) $this->route('id'));

        return $template === null || ($this->user()?->can('update', $template) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'document' => ['sometimes', 'array'],
        ];
    }
}
