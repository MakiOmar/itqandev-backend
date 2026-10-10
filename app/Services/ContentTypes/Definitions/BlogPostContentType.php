<?php

namespace App\Services\ContentTypes\Definitions;

use App\Models\BlogPost;
use App\Services\ContentTypes\BaseContentType;
use App\Services\ContentTypes\RelationTags;
use App\Support\TranslatableContentPresenter;
use Illuminate\Database\Eloquent\Model;

final class BlogPostContentType extends BaseContentType
{
    public function key(): string
    {
        return 'blog_post';
    }

    public function label(): string
    {
        return 'Blog post';
    }

    public function pluralLabel(): string
    {
        return 'Blog posts';
    }

    public function modelClass(): string
    {
        return BlogPost::class;
    }

    public function module(): ?string
    {
        return 'blog';
    }

    public function publicPathPrefixes(): array
    {
        return ['blog'];
    }

    public function loopSource(): ?string
    {
        return 'blog';
    }

    public function archive(): ?array
    {
        return ['context' => 'blog_index', 'page_slug' => 'articles', 'label' => 'Blog'];
    }

    protected function applyTranslation(Model $record, string $locale): void
    {
        TranslatableContentPresenter::applyBlogPost($record, $locale);
    }

    protected function postFields(): array
    {
        return [
            'title' => 'title',
            'excerpt' => 'excerpt',
            'content' => 'content',
            'image' => static fn (BlogPost $p) => self::mediaUrl($p, 'featured_image'),
            'date' => 'published_at',
        ];
    }

    protected function fields(): array
    {
        return [
            'featured' => ['Featured', 'text', static fn (BlogPost $p) => (bool) $p->featured],
        ];
    }

    protected function extraEagerLoads(): array
    {
        return ['media'];
    }

    public function relations(): array
    {
        return [
            // Only the display name: never expose author email or other account fields.
            RelationTags::one('author', 'Author', ['name' => ['Name']]),
        ];
    }
}
