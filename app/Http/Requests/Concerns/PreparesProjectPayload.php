<?php

namespace App\Http\Requests\Concerns;

use App\Models\Project;
use App\Support\UniqueContentSlug;
use Illuminate\Validation\Rule;

trait PreparesProjectPayload
{
    /** Ordered gallery: existing media library images only. */
    protected function galleryRules(): array
    {
        return [
            'gallery_media_ids' => ['nullable', 'array', 'max:'.Project::GALLERY_MAX],
            'gallery_media_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('media', 'id')->where(fn ($q) => $q->where('mime_type', 'like', 'image/%')),
            ],
        ];
    }

    protected function mergeProjectAliases(): void
    {
        $this->merge([
            'link_url' => $this->input('link_url') ?: $this->input('linkUrl'),
            'repo_url' => $this->input('repo_url') ?: $this->input('repoUrl'),
            'demo_url' => $this->input('demo_url') ?: $this->input('demoUrl'),
            'published_at' => $this->input('published_at') ?: $this->input('publishedAt'),
        ]);
    }

    protected function mergeUniqueProjectSlug(?int $ignoreId = null, bool $onlyWhenPresent = false): void
    {
        if ($onlyWhenPresent && ! $this->exists('slug')) {
            return;
        }

        $source = trim((string) $this->input('slug', ''));
        if ($source === '') {
            $source = trim((string) $this->input('title', ''));
        }

        $this->merge([
            'slug' => UniqueContentSlug::fromSource(Project::class, $source, $ignoreId),
        ]);
    }
}
