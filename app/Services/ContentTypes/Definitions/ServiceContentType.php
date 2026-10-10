<?php

namespace App\Services\ContentTypes\Definitions;

use App\Models\Service;
use App\Services\ContentTypes\BaseContentType;
use App\Support\TranslatableContentPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class ServiceContentType extends BaseContentType
{
    public function key(): string
    {
        return 'service';
    }

    public function label(): string
    {
        return 'Service';
    }

    public function pluralLabel(): string
    {
        return 'Services';
    }

    public function modelClass(): string
    {
        return Service::class;
    }

    public function module(): ?string
    {
        return 'services';
    }

    public function publishedQuery(): Builder
    {
        return Service::query()->where('is_published', true);
    }

    public function publicPathPrefixes(): array
    {
        return ['services'];
    }

    public function loopSource(): ?string
    {
        return 'services';
    }

    public function archive(): ?array
    {
        return ['context' => 'services_index', 'page_slug' => 'services', 'label' => 'Services'];
    }

    protected function applyTranslation(Model $record, string $locale): void
    {
        TranslatableContentPresenter::applyService($record, $locale);
    }

    protected function postFields(): array
    {
        return [
            'title' => 'name',
            'excerpt' => 'short_description',
            'content' => 'description',
            'image' => 'icon',
        ];
    }

    protected function fields(): array
    {
        return [
            'icon' => ['Icon'],
        ];
    }
}
