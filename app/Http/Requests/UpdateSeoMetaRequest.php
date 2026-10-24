<?php

namespace App\Http\Requests;

use App\Models\SeoMeta;
use App\Support\SeoDefaults;
use App\Support\SiteLanguages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Per-locale SEO row for one record (`PUT /v1/seo/{type}/{id}`). The parent record's own
 * `update` policy is checked in the controller once the model is resolved.
 */
class UpdateSeoMetaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', new SeoMeta) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $enabledLocales = array_values(array_map(static fn (array $row) => $row['code'], SiteLanguages::all()));
        // http(s) URLs or same-site paths only; javascript:, data: and //host never reach public heads.
        $safeUrl = 'regex:#^(https?://|/(?!/))#i';

        return [
            'locale' => ['required', 'string', 'max:16', Rule::in($enabledLocales)],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:512'],
            'canonical_url' => ['nullable', 'string', 'max:2048', $safeUrl],
            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string', 'max:512'],
            'og_image' => ['nullable', 'string', 'max:2048', $safeUrl],
            'twitter_card' => ['nullable', 'string', Rule::in(SeoDefaults::TWITTER_CARDS)],
            'schema' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('locale'))) {
            $this->merge(['locale' => strtolower(trim($this->input('locale')))]);
        }
    }
}
