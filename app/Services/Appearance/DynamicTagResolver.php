<?php

namespace App\Services\Appearance;

use App\Models\BlogPost;
use App\Models\Page;
use App\Models\Project;
use App\Models\Service;
use App\Support\ProjectSettingsStore;
use App\Support\TranslatableContentPresenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Server-side, locale-aware replacement of {{tag}} tokens in builder strings.
 */
final class DynamicTagResolver
{
    /**
     * @param  array<string, mixed>|null  $recordPayload
     */
    public static function apply(mixed $value, ?array $recordPayload, ?string $locale = null): mixed
    {
        if (is_string($value)) {
            return self::replaceString($value, $recordPayload, $locale);
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = self::apply($v, $recordPayload, $locale);
            }

            return $out;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>|null  $recordPayload
     */
    public static function replaceString(string $value, ?array $recordPayload, ?string $locale = null): string
    {
        if (! str_contains($value, '{{')) {
            return $value;
        }

        return (string) preg_replace_callback('/\{\{\s*([a-z0-9_.]+)\s*\}\}/i', function (array $m) use ($recordPayload, $locale) {
            $id = strtolower($m[1]);
            if (! DynamicTagRegistry::isAllowed($id)) {
                return '';
            }

            return self::resolve($id, $recordPayload, $locale);
        }, $value) ?? $value;
    }

    /**
     * @param  array<string, mixed>|null  $recordPayload
     */
    public static function resolve(string $id, ?array $recordPayload, ?string $locale = null): string
    {
        $settings = ProjectSettingsStore::load();
        $record = is_array($recordPayload) ? $recordPayload : [];

        return match ($id) {
            'site.name' => (string) ($settings['site_name'] ?? $settings['name'] ?? ''),
            'site.tagline' => (string) ($settings['site_description'] ?? $settings['description'] ?? ''),
            'site.url' => rtrim((string) config('app.url'), '/'),
            'site.logo' => (string) ($settings['logo'] ?? $settings['site_logo'] ?? ''),
            'site.year' => (string) Carbon::now()->year,
            'post.title' => (string) ($record['title'] ?? $record['name'] ?? ''),
            'post.excerpt' => (string) ($record['excerpt'] ?? $record['summary'] ?? $record['short_description'] ?? ''),
            'post.content' => (string) ($record['content'] ?? $record['description'] ?? ''),
            'post.url' => (string) ($record['url'] ?? ''),
            'post.image' => (string) ($record['image'] ?? $record['featured_image'] ?? ''),
            'post.date' => (string) ($record['date'] ?? $record['published_at'] ?? ''),
            'post.terms' => (string) ($record['terms'] ?? ''),
            'archive.title' => (string) ($record['archive_title'] ?? $record['title'] ?? ''),
            default => '',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function payloadFromModel(?Model $record, string $context, ?string $locale = null): array
    {
        $record = self::localizedRecord($record, $locale);
        if ($record instanceof BlogPost) {
            return [
                'title' => (string) $record->title,
                'excerpt' => (string) ($record->excerpt ?? ''),
                'content' => (string) ($record->content ?? ''),
                'url' => '/blog/'.$record->slug,
                'image' => (string) ($record->featured_image ?? $record->cover_image ?? ''),
                'date' => optional($record->published_at)->toDateString() ?? '',
            ];
        }
        if ($record instanceof Project) {
            return [
                'title' => (string) $record->title,
                'excerpt' => (string) ($record->summary ?? ''),
                'content' => (string) ($record->description ?? ''),
                'url' => '/work/'.$record->slug,
                'image' => (string) ($record->cover_image ?? ''),
                'date' => optional($record->published_at)->toDateString() ?? '',
            ];
        }
        if ($record instanceof Service) {
            return [
                'title' => (string) $record->name,
                'excerpt' => (string) ($record->short_description ?? ''),
                'content' => (string) ($record->description ?? ''),
                'url' => '/services/'.$record->slug,
                'image' => (string) ($record->icon ?? ''),
            ];
        }
        if ($record instanceof Page) {
            return [
                'title' => (string) $record->title,
                'excerpt' => (string) ($record->excerpt ?? ''),
                'content' => (string) ($record->content ?? ''),
                'url' => '/pages/'.$record->slug,
            ];
        }

        $archiveTitle = match ($context) {
            'blog_index' => 'Blog',
            'portfolio_index' => 'Portfolio',
            'services_index' => 'Services',
            'not_found' => 'Not found',
            'homepage' => (string) (ProjectSettingsStore::load()['site_name'] ?? ''),
            default => '',
        };

        return [
            'archive_title' => $archiveTitle,
            'title' => $archiveTitle,
        ];
    }

    /** Copy of the record with the locale's translation applied; the caller's model stays untouched. */
    private static function localizedRecord(?Model $record, ?string $locale): ?Model
    {
        $locale = strtolower(trim((string) $locale));
        if ($record === null || $locale === '') {
            return $record;
        }
        $copy = clone $record;
        match (true) {
            $copy instanceof BlogPost => TranslatableContentPresenter::applyBlogPost($copy, $locale),
            $copy instanceof Project => TranslatableContentPresenter::applyProject($copy, $locale),
            $copy instanceof Service => TranslatableContentPresenter::applyService($copy, $locale),
            $copy instanceof Page => TranslatableContentPresenter::applyPage($copy, $locale),
            default => null,
        };

        return $copy;
    }
}
