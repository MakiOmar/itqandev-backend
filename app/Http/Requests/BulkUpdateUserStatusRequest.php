<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('bulkUpdate', User::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'status' => ['required', 'string', Rule::in(User::STATUSES)],
        ];
    }
}
