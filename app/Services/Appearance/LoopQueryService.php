<?php

namespace App\Services\Appearance;

use App\Models\BlogPost;
use App\Models\Project;
use App\Models\Service;
use App\Support\FeatureModules;

/**
 * Query published records for the loop_grid widget (eager-loaded, capped).
 */
final class LoopQueryService
{
    /**
     * @param  array<string, mixed>  $settings
     * @return list<array<string, mixed>>
     */
    public static function items(array $settings, ?string $locale = null): array
    {
        $source = strtolower(trim((string) ($settings['source'] ?? 'blog')));
        $count = max(1, min(24, (int) ($settings['count'] ?? 6)));
        $order = strtolower(trim((string) ($settings['order'] ?? 'latest')));

        return match ($source) {
            'projects' => FeatureModules::enabled('projects') ? self::projects($count, $order) : [],
            'services' => FeatureModules::enabled('services') ? self::services($count, $order) : [],
            default => FeatureModules::enabled('blog') ? self::posts($count, $order) : [],
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function posts(int $count, string $order): array
    {
        $q = BlogPost::query()->where('status', 'published')->limit($count);
        $q = self::order($q, $order, 'title');

        return $q->get(['id', 'title', 'slug', 'excerpt', 'published_at'])->map(fn (BlogPost $p) => [
            'id' => $p->id,
            'title' => $p->title,
            'excerpt' => $p->excerpt,
            'url' => '/blog/'.$p->slug,
            'date' => optional($p->published_at)->toDateString(),
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function projects(int $count, string $order): array
    {
        $q = Project::query()->where('status', 'published')->limit($count);
        $q = self::order($q, $order, 'title');

        return $q->get(['id', 'title', 'slug', 'summary', 'published_at'])->map(fn (Project $p) => [
            'id' => $p->id,
            'title' => $p->title,
            'excerpt' => $p->summary,
            'url' => '/work/'.$p->slug,
            'date' => optional($p->published_at)->toDateString(),
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function services(int $count, string $order): array
    {
        $q = Service::query()->where('is_published', true)->limit($count);
        $q = self::order($q, $order, 'name');

        return $q->get(['id', 'name', 'slug', 'short_description'])->map(fn (Service $s) => [
            'id' => $s->id,
            'title' => $s->name,
            'excerpt' => $s->short_description,
            'url' => '/services/'.$s->slug,
        ])->all();
    }

    private static function order($query, string $order, string $titleCol)
    {
        return match ($order) {
            'oldest' => $query->orderBy('id'),
            'title' => $query->orderBy($titleCol),
            default => $query->orderByDesc('id'),
        };
    }
}
