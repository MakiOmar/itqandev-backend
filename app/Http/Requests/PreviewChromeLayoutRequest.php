<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesChromeLayoutKind;
use App\Services\ContentTypes\ContentTypeRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Builder "Preview as": present unsaved layout sections with a record's dynamic tag values.
 * Nothing is stored; sections go through the same normalizer as a save.
 */
class PreviewChromeLayoutRequest extends FormRequest
{
    use ResolvesChromeLayoutKind;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'content_type' => ['required', 'string', Rule::in(app(ContentTypeRegistry::class)->keys())],
            'record_id' => ['required', 'integer', 'min:1'],
            'sections' => ['present', 'array', 'max:200'],
            'locale' => ['nullable', 'string', 'max:10', 'regex:/^[a-z]{2,3}([_-][a-z0-9]{2,8})?$/i'],
        ];
    }
}
