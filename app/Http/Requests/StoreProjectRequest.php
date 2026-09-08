<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\PreparesProjectPayload;
use App\Models\Project;
use App\Rules\UrlOrHash;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    use PreparesProjectPayload;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Project::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->mergeProjectAliases();
        $this->mergeUniqueProjectSlug();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:projects,slug'],
            'summary' => ['nullable', 'string', 'max:1024'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', 'max:40'],
            'link_url' => ['nullable', new UrlOrHash],
            'repo_url' => ['nullable', new UrlOrHash],
            'demo_url' => ['nullable', new UrlOrHash],
            'featured' => ['boolean'],
            'published_at' => ['nullable', 'date'],
            'content_locale' => ['nullable', 'string', 'max:16'],
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'skill_ids' => ['array'],
            'skill_ids.*' => ['integer', 'exists:skills,id'],
            'translations' => ['nullable', 'array'],
            'translations.*.locale' => ['required', 'string', 'max:16'],
            'translations.*.title' => ['nullable', 'string', 'max:255'],
            'translations.*.summary' => ['nullable', 'string', 'max:1024'],
            'translations.*.description' => ['nullable', 'string'],
            'header_layout_id' => ['nullable', 'integer'],
            'footer_layout_id' => ['nullable', 'integer'],
        ];
    }
}
