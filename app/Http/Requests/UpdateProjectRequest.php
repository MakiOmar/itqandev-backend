<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\PreparesProjectPayload;
use App\Models\Project;
use App\Rules\UrlOrHash;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    use PreparesProjectPayload;

    public function authorize(): bool
    {
        $project = $this->route('project');
        if (! $project instanceof Project || ! $project->exists) {
            $id = $this->route('project');
            $project = Project::query()->find($id);
        }

        return $project instanceof Project
            && ($this->user()?->can('update', $project) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->mergeProjectAliases();

        $project = $this->route('project');
        $ignoreId = $project instanceof Project ? (int) $project->id : (int) $this->route('project');
        $this->mergeUniqueProjectSlug($ignoreId > 0 ? $ignoreId : null, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $project = $this->route('project');
        $id = $project instanceof Project ? $project->id : $this->route('project');

        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('projects')->ignore($id)],
            'summary' => ['nullable', 'string', 'max:1024'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', 'max:40'],
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
