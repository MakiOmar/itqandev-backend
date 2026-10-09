<?php

namespace App\Http\Requests;

use App\Models\ChromeLayout;
use Illuminate\Validation\Rule;

class BulkUpdateChromeLayoutStatusRequest extends BulkChromeLayoutsRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'status' => ['required', 'string', Rule::in([ChromeLayout::STATUS_DRAFT, ChromeLayout::STATUS_PUBLISHED])],
        ];
    }
}
