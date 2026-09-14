<?php

namespace App\Http\Requests;

use App\Models\BuilderGlobal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBuilderGlobalRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'status' => ['sometimes', 'string', Rule::in([BuilderGlobal::STATUS_DRAFT, BuilderGlobal::STATUS_PUBLISHED])],
            'document' => ['required', 'array'],
        ];
    }
}
