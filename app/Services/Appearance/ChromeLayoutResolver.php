<?php

namespace App\Services\Appearance;

use App\Models\BlogPost;
use App\Models\ChromeLayout;
use App\Models\Page;
use App\Models\Project;
use App\Models\Service;
use App\Models\ThemeTemplate;
use App\Support\CmsPublicPaths;
use App\Support\SiteLanguages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves the public header/footer/body documents for a content context.
 *
 * Cascade per chrome kind: record FK → theme template slot → chrome_type_defaults
 * → published site default → builder defaultDocument.
 *
 * Body layouts apply only for homepage / not_found contexts (v1).
 */
final class ChromeLayoutResolver
{
    public function __construct(
        private readonly ChromeLayoutService $layouts,
        private readonly ThemeTemplateService $themeTemplates,
    ) {}

    /**
     * @return array{sections: list<array<string, mixed>>}
     */
    public function resolve(string $kind, string $contentType, ?Model $record = null, ?string $locale = null, ?ThemeTemplate $matched = null): array
    {
        $kind = match ($kind) {
            ChromeLayout::KIND_FOOTER => ChromeLayout::KIND_FOOTER,
            ChromeLayout::KIND_BODY => ChromeLayout::KIND_BODY,
            default => ChromeLayout::KIND_HEADER,
        };
        $locale = $locale !== null && $locale !== ''
            ? strtolower(trim($locale))
            : SiteLanguages::defaultCode();

        if ($kind !== ChromeLayout::KIND_BODY) {
            $fromRecord = $this->resolveFromRecord($kind, $record);
            if ($fromRecord !== null) {
                return $this->layouts->presentById($fromRecord, $locale);
            }
        }

        if ($matched !== null) {
            $slotId = match ($kind) {
                ChromeLayout::KIND_FOOTER => $matched->footer_layout_id,
                ChromeLayout::KIND_BODY => $matched->body_layout_id,
                default => $matched->header_layout_id,
            };
            if ($slotId !== null && (int) $slotId > 0) {
                $usable = $this->usableLayoutId((int) $slotId, $kind);
                if ($usable !== null) {
                    return $this->layouts->presentById($usable, $locale);
                }
            }
        }

        if ($kind !== ChromeLayout::KIND_BODY) {
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

        return ['sections' => []];
    }

    /**
     * Infer content context from a public document URL/path and resolve chrome + optional theme body.
     *
     * @return array{
     *   content_type: string,
     *   context: string,
     *   record: ?Model,
     *   theme_template_id: int|null,
     *   header: array{sections: list<array<string, mixed>>},
     *   footer: array{sections: list<array<string, mixed>>},
     *   theme_body: array{sections: list<array<string, mixed>>}|null
     * }
     */
    public function resolveForDocumentPath(
        ?string $documentUrlOrPath,
        ?string $locale = null,
        ?Request $request = null,
        ?string $forcedContext = null,
    ): array {
        [$contentType, $record, $routeContext] = $this->contextFromPath($documentUrlOrPath);

        if ($forcedContext !== null && trim($forcedContext) !== '') {
            $routeContext = strtolower(trim($forcedContext));
            if ($routeContext === 'not_found') {
                $contentType = 'homepage';
                $record = null;
            }
        }

        $matcherCtx = ThemeTemplateConditions::contextFromResolver(
            $contentType,
            $record,
            $routeContext,
            $request
        );
        $matched = $this->themeTemplates->findBestMatch($matcherCtx);

        $header = $this->resolve(ChromeLayout::KIND_HEADER, $contentType, $record, $locale, $matched);
        $footer = $this->resolve(ChromeLayout::KIND_FOOTER, $contentType, $record, $locale, $matched);

        $themeBody = null;
        if ($matched !== null
            && ThemeTemplateService::bodyAppliesForContext($routeContext)
            && $matched->body_layout_id !== null
            && (int) $matched->body_layout_id > 0
        ) {
            $bodyDoc = $this->resolve(ChromeLayout::KIND_BODY, $contentType, $record, $locale, $matched);
            if (($bodyDoc['sections'] ?? []) !== []) {
                $themeBody = $bodyDoc;
            }
        }

        return [
            'content_type' => $contentType,
            'context' => $routeContext,
            'record' => $record,
            'theme_template_id' => $matched !== null ? (int) $matched->id : null,
            'header' => $header,
            'footer' => $footer,
            'theme_body' => $themeBody,
        ];
    }

    /**
     * @return array{0: string, 1: ?Model, 2: string} content_type, record, route_context
     */
    public function contextFromPath(?string $documentUrlOrPath): array
    {
        $path = $this->normalizePath($documentUrlOrPath);
        if ($path === '/' || $path === '') {
            return ['homepage', null, 'homepage'];
        }

        if (preg_match('#^/([a-z]{2})(/.*)?$#i', $path, $m)) {
            $rest = $m[2] ?? '/';
            $path = $rest === '' ? '/' : $rest;
        }
        $path = '/'.trim($path, '/');
        if ($path === '/') {
            return ['homepage', null, 'homepage'];
        }

        if (! Schema::hasTable('chrome_layouts')) {
            return ['homepage', null, 'homepage'];
        }

        // Archive / listing indexes (before slug singles)
        if (preg_match('#^/blog/?$#i', $path)) {
            $page = $this->publishedPageBySlug('articles');

            return ['page', $page, 'blog_index'];
        }
        if (preg_match('#^/(portfolio|work)/?$#i', $path)) {
            $page = $this->publishedPageBySlug('portfolio');

            return ['page', $page, 'portfolio_index'];
        }
        if (preg_match('#^/services/?$#i', $path)) {
            $page = $this->publishedPageBySlug('services');

            return ['page', $page, 'services_index'];
        }

        if (preg_match('#^/blog/([^/]+)/?$#i', $path, $m)) {
            $post = BlogPost::query()
                ->where('slug', $m[1])
                ->where('status', 'published')
                ->first(['id', 'header_layout_id', 'footer_layout_id']);

            return ['blog_post', $post, 'blog_post'];
        }

        if (preg_match('#^/(portfolio|work)/([^/]+)/?$#i', $path, $m)) {
            $project = Project::query()
                ->where('slug', $m[2])
                ->where('status', 'published')
                ->first(['id', 'header_layout_id', 'footer_layout_id']);

            return ['project', $project, 'project'];
        }

        if (preg_match('#^/services/([^/]+)/?$#i', $path, $m)) {
            $service = Service::query()
                ->where('slug', $m[1])
                ->where('is_published', true)
                ->first(['id', 'header_layout_id', 'footer_layout_id']);

            return ['service', $service, 'service'];
        }

        if (preg_match('#^/pages/([^/]+)/?$#i', $path, $m)) {
            $page = Page::query()
                ->where('slug', $m[1])
                ->where('status', Page::STATUS_PUBLISHED)
                ->first(['id', 'header_layout_id', 'footer_layout_id']);

            return ['page', $page, 'page'];
        }

        $pretty = CmsPublicPaths::prettyPathsBySlug();
        foreach ($pretty as $slug => $prettyPath) {
            $normalizedPretty = '/'.trim((string) $prettyPath, '/');
            if ($normalizedPretty === $path) {
                $page = Page::query()
                    ->where('slug', $slug)
                    ->where('status', Page::STATUS_PUBLISHED)
                    ->first(['id', 'header_layout_id', 'footer_layout_id']);

                $routeContext = match ($slug) {
                    'articles' => 'blog_index',
                    'portfolio' => 'portfolio_index',
                    'services' => 'services_index',
                    default => 'page',
                };

                return ['page', $page, $routeContext];
            }
        }

        // Unknown public path → not_found (not homepage chrome)
        return ['homepage', null, 'not_found'];
    }

    private function publishedPageBySlug(string $slug): ?Page
    {
        if (! Schema::hasTable('pages')) {
            return null;
        }

        return Page::query()
            ->where('slug', $slug)
            ->where('status', Page::STATUS_PUBLISHED)
            ->first(['id', 'header_layout_id', 'footer_layout_id']);
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
