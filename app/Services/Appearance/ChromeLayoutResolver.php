<?php

namespace App\Services\Appearance;

use App\Models\BlogPost;
use App\Models\ChromeLayout;
use App\Models\Page;
use App\Models\Project;
use App\Models\Service;
use App\Support\CmsPublicPaths;
use App\Support\SiteLanguages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves the public header/footer document for a content context.
 *
 * Cascade: record FK → chrome_type_defaults → published site default → builder defaultDocument.
 */
final class ChromeLayoutResolver
{
    public function __construct(
        private readonly ChromeLayoutService $layouts,
    ) {}

    /**
     * @return array{sections: list<array<string, mixed>>}
     */
    public function resolve(string $kind, string $contentType, ?Model $record = null, ?string $locale = null): array
    {
        $kind = $kind === ChromeLayout::KIND_FOOTER
            ? ChromeLayout::KIND_FOOTER
            : ChromeLayout::KIND_HEADER;
        $locale = $locale !== null && $locale !== ''
            ? strtolower(trim($locale))
            : SiteLanguages::defaultCode();

        $fromRecord = $this->resolveFromRecord($kind, $record);
        if ($fromRecord !== null) {
            return $this->layouts->presentById($fromRecord, $locale);
        }

        $fromType = $this->resolveFromTypeDefaults($kind, $contentType);
        if ($fromType !== null) {
            return $this->layouts->presentById($fromType, $locale);
        }

        $siteDefault = $this->layouts->findSiteDefault($kind);
        if ($siteDefault !== null) {
            return $this->layouts->presentById((int) $siteDefault->id, $locale);
        }

        $fallback = $kind === ChromeLayout::KIND_FOOTER
            ? app(FooterBuilderService::class)->defaultDocument()
            : app(HeaderBuilderService::class)->defaultDocument();

        return ChromeLayoutSupport::presentPublic($fallback, $locale);
    }

    /**
     * Infer content context from a public document URL/path and resolve both chrome kinds.
     *
     * @return array{content_type: string, record: ?Model, header: array{sections: list<array<string, mixed>>}, footer: array{sections: list<array<string, mixed>>}}
     */
    public function resolveForDocumentPath(?string $documentUrlOrPath, ?string $locale = null): array
    {
        [$contentType, $record] = $this->contextFromPath($documentUrlOrPath);

        return [
            'content_type' => $contentType,
            'record' => $record,
            'header' => $this->resolve(ChromeLayout::KIND_HEADER, $contentType, $record, $locale),
            'footer' => $this->resolve(ChromeLayout::KIND_FOOTER, $contentType, $record, $locale),
        ];
    }

    /**
     * @return array{0: string, 1: ?Model}
     */
    public function contextFromPath(?string $documentUrlOrPath): array
    {
        $path = $this->normalizePath($documentUrlOrPath);
        if ($path === '/' || $path === '') {
            return ['homepage', null];
        }

        if (preg_match('#^/([a-z]{2})(/.*)?$#i', $path, $m)) {
            $rest = $m[2] ?? '/';
            $path = $rest === '' ? '/' : $rest;
        }
        $path = '/'.trim($path, '/');
        if ($path === '/') {
            return ['homepage', null];
        }

        if (! Schema::hasTable('chrome_layouts')) {
            return ['homepage', null];
        }

        if (preg_match('#^/blog/([^/]+)/?$#i', $path, $m)) {
            $post = BlogPost::query()
                ->where('slug', $m[1])
                ->where('status', 'published')
                ->first(['id', 'header_layout_id', 'footer_layout_id']);

            return ['blog_post', $post];
        }

        if (preg_match('#^/(portfolio|work)/([^/]+)/?$#i', $path, $m)) {
            $project = Project::query()
                ->where('slug', $m[2])
                ->where('status', 'published')
                ->first(['id', 'header_layout_id', 'footer_layout_id']);

            return ['project', $project];
        }

        if (preg_match('#^/services/([^/]+)/?$#i', $path, $m)) {
            $service = Service::query()
                ->where('slug', $m[1])
                ->where('is_published', true)
                ->first(['id', 'header_layout_id', 'footer_layout_id']);

            return ['service', $service];
        }

        if (preg_match('#^/pages/([^/]+)/?$#i', $path, $m)) {
            $page = Page::query()
                ->where('slug', $m[1])
                ->where('status', Page::STATUS_PUBLISHED)
                ->first(['id', 'header_layout_id', 'footer_layout_id']);

            return ['page', $page];
        }

        $pretty = CmsPublicPaths::prettyPathsBySlug();
        foreach ($pretty as $slug => $prettyPath) {
            $normalizedPretty = '/'.trim((string) $prettyPath, '/');
            if ($normalizedPretty === $path) {
                $page = Page::query()
                    ->where('slug', $slug)
                    ->where('status', Page::STATUS_PUBLISHED)
                    ->first(['id', 'header_layout_id', 'footer_layout_id']);

                return ['page', $page];
            }
        }

        return ['homepage', null];
    }

    private function resolveFromRecord(string $kind, ?Model $record): ?int
    {
        if ($record === null) {
            return null;
        }

        $attr = $kind === ChromeLayout::KIND_FOOTER ? 'footer_layout_id' : 'header_layout_id';
        $id = $record->getAttribute($attr);
        if ($id === null || $id === '' || (int) $id <= 0) {
            return null;
        }

        return $this->usableLayoutId((int) $id, $kind);
    }

    private function resolveFromTypeDefaults(string $kind, string $contentType): ?int
    {
        $contentType = strtolower(trim($contentType));
        if (! in_array($contentType, ChromeLayoutService::CONTENT_TYPES, true)) {
            return null;
        }

        $defaults = $this->layouts->getTypeDefaults();
        $key = $kind === ChromeLayout::KIND_FOOTER ? 'footer_id' : 'header_id';
        $id = $defaults[$contentType][$key] ?? null;
        if ($id === null || (int) $id <= 0) {
            return null;
        }

        return $this->usableLayoutId((int) $id, $kind);
    }

    private function usableLayoutId(int $id, string $kind): ?int
    {
        if (! Schema::hasTable('chrome_layouts')) {
            return null;
        }

        $layout = ChromeLayout::query()->find($id);
        if ($layout === null) {
            return null;
        }
        if ($layout->kind !== $kind) {
            return null;
        }
        if (! $layout->isPublished()) {
            return null;
        }

        return (int) $layout->id;
    }

    private function normalizePath(?string $documentUrlOrPath): string
    {
        if ($documentUrlOrPath === null || trim($documentUrlOrPath) === '') {
            return '/';
        }
        $raw = trim($documentUrlOrPath);
        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
            $parts = parse_url($raw);
            $raw = (string) ($parts['path'] ?? '/');
        }
        $path = '/'.trim($raw, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
