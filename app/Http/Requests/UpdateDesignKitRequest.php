<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDesignKitRequest extends FormRequest
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
        $hex = 'regex:/^#(?:[A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/';

        return [
            'colors' => ['sometimes', 'array'],
            'colors.primary' => ['nullable', 'string', $hex],
            'colors.secondary' => ['nullable', 'string', $hex],
            'colors.text' => ['nullable', 'string', $hex],
            'colors.accent' => ['nullable', 'string', $hex],
            'colors.muted' => ['nullable', 'string', $hex],
            'colors.custom' => ['sometimes', 'array', 'max:16'],
            'colors.custom.*.id' => ['required_with:colors.custom', 'string', 'max:32'],
            'colors.custom.*.value' => ['required_with:colors.custom', 'string', $hex],
            // Keyed by colour id (base or custom); unknown ids are dropped by DesignKitResolver.
            'colors_dark' => ['sometimes', 'array', 'max:21'],
            'colors_dark.*' => ['nullable', 'string', $hex],
            'type_roles' => ['sometimes', 'array'],
            'type_roles.heading' => ['sometimes', 'array'],
            'type_roles.body' => ['sometimes', 'array'],
            'type_roles.accent' => ['sometimes', 'array'],
        ];
    }
}
