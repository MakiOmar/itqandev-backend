<?php

namespace App\Http\Requests;

use App\Models\Testimonial;
use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateTestimonialApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('bulkUpdate', Testimonial::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'distinct', 'exists:testimonials,id'],
            'approved' => ['required', 'boolean'],
        ];
    }
}
