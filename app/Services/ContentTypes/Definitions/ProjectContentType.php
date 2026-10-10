<?php

namespace App\Services\ContentTypes\Definitions;

use App\Models\Category;
use App\Models\Project;
use App\Models\Skill;
use App\Models\Testimonial;
use App\Services\ContentTypes\BaseContentType;
use App\Services\ContentTypes\RelationTags;
use App\Support\TranslatableContentPresenter;
use Illuminate\Database\Eloquent\Model;

final class ProjectContentType extends BaseContentType
{
    public function key(): string
    {
        return 'project';
    }

    public function label(): string
    {
        return 'Project';
    }

    public function pluralLabel(): string
    {
        return 'Projects';
    }

    public function modelClass(): string
    {
        return Project::class;
    }

    public function module(): ?string
    {
        return 'projects';
    }

    public function publicPathPrefixes(): array
    {
        return ['work', 'portfolio'];
    }

    public function loopSource(): ?string
    {
        return 'projects';
    }

    public function archive(): ?array
    {
        return ['context' => 'portfolio_index', 'page_slug' => 'portfolio', 'label' => 'Portfolio'];
    }

    protected function applyTranslation(Model $record, string $locale): void
    {
        TranslatableContentPresenter::applyProject($record, $locale);
    }

    protected function postFields(): array
    {
        return [
            'title' => 'title',
            'excerpt' => 'summary',
            'content' => 'description',
            'image' => static fn (Project $p) => self::mediaUrl($p, 'hero'),
            'date' => 'published_at',
        ];
    }

    protected function termsRelation(): ?string
    {
        return 'categories';
    }

    protected function fields(): array
    {
        return [
            'link_url' => ['Link URL', 'url'],
            'repo_url' => ['Repository URL', 'url'],
            'demo_url' => ['Demo URL', 'url'],
            'featured' => ['Featured', 'text', static fn (Project $p) => (bool) $p->featured],
            'date' => ['Published date', 'date', 'published_at'],
        ];
    }

    protected function extraEagerLoads(): array
    {
        return ['media'];
    }

    public function relations(): array
    {
        return [
            RelationTags::many(
                'categories',
                'Categories',
                [
                    'name' => ['Name'],
                    'slug' => ['Slug'],
                    'description' => ['Description'],
                    'image' => ['Image URL', 'image', static fn (Category $c) => self::mediaUrl($c, 'thumb')],
                ],
                url: static fn (Category $c) => '/portfolio?category_slug='.rawurlencode((string) $c->slug),
                localize: static fn (Category $c, string $locale) => TranslatableContentPresenter::applyCategory($c, $locale),
                with: ['media'],
            ),
            RelationTags::many(
                'skills',
                'Skills',
                [
                    'name' => ['Name'],
                    'slug' => ['Slug'],
                    'description' => ['Description'],
                ],
                url: static fn (Skill $s) => '/portfolio?skill_slug='.rawurlencode((string) $s->slug),
                localize: static fn (Skill $s, string $locale) => TranslatableContentPresenter::applySkill($s, $locale),
            ),
            RelationTags::many(
                'testimonials',
                'Testimonials',
                [
                    'quote' => ['Quote', 'text', 'content'],
                    'author' => ['Author', 'text', 'client_name'],
                    'role' => ['Author role', 'text', 'client_role'],
                    'company' => ['Company'],
                ],
                localize: static fn (Testimonial $t, string $locale) => TranslatableContentPresenter::applyTestimonial($t, $locale),
                listField: null,
                filter: static fn (Testimonial $t) => (bool) $t->approved,
            ),
        ];
    }
}
