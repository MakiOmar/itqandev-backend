<?php

namespace App\Services\ContentTypes\Definitions;

use App\Models\Page;
use App\Services\ContentTypes\BaseContentType;
use App\Services\ContentTypes\RelationTags;
use App\Support\TranslatableContentPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class PageContentType extends BaseContentType
{
    public function key(): string
    {
        return 'page';
    }

    public function label(): string
    {
        return 'Page';
    }

    public function pluralLabel(): string
    {
        return 'Pages';
    }

    public function modelClass(): string
    {
        return Page::class;
    }

    public function module(): ?string
    {
        return 'pages';
    }

    public function publishedQuery(): Builder
    {
        return Page::query()->where('status', Page::STATUS_PUBLISHED);
    }

    public function publicPathPrefixes(): array
    {
        return ['pages'];
    }

    protected function applyTranslation(Model $record, string $locale): void
    {
        TranslatableContentPresenter::applyPage($record, $locale);
    }

    protected function postFields(): array
    {
        return [
            'title' => 'title',
            'subtitle' => 'subtitle',
            'excerpt' => 'excerpt',
            'content' => 'content',
        ];
    }

    public function relations(): array
    {
        return [
            RelationTags::one(
                'parent',
                'Parent page',
                ['title' => ['Title']],
                url: fn (Page $p) => $this->publicUrl($p),
                localize: static fn (Page $p, string $locale) => TranslatableContentPresenter::applyPage($p, $locale),
            ),
        ];
    }
}
