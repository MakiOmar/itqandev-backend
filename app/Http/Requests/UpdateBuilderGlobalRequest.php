<?php

namespace App\Http\Requests;

use App\Models\BuilderGlobal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBuilderGlobalRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:120'],
            'status' => ['sometimes', 'string', Rule::in([BuilderGlobal::STATUS_DRAFT, BuilderGlobal::STATUS_PUBLISHED])],
            'document' => ['sometimes', 'array'],
            'type' => ['sometimes', 'string', 'max:64'],
            'kind' => ['sometimes', 'string', 'max:32'],
            'settings' => ['sometimes', 'array'],
        ];
    }
}
