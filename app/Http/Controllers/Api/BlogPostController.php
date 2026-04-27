<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Services\HtmlSanitizerService;
use App\Support\SiteLanguages;
use App\Support\TranslatableContentPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BlogPostController extends Controller
{
    public function __construct(
        protected HtmlSanitizerService $sanitizer
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', BlogPost::class);

        $present = TranslatableContentPresenter::requestedPresentationLocale($request);
        $siteDefaultLocale = SiteLanguages::defaultCode();
        $cacheKey = 'blog_posts:list:'.md5(json_encode($request->query())).':loc:'.($present ?? 'none');

        $paginator = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($request, $present, $siteDefaultLocale) {
            $query = BlogPost::with('author:id,name,email', 'translations');

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('featured')) {
                $query->where('featured', $request->boolean('featured'));
            }

            if ($request->has('author_id')) {
                $query->where('author_id', $request->author_id);
            }

            if ($present) {
                $query->where(function ($q) use ($present, $siteDefaultLocale) {
                    $q->where('content_locale', $present);

                    if ($present === $siteDefaultLocale) {
                        $q->orWhereNull('content_locale');
                    }

                    $q->orWhereHas('translations', function ($tq) use ($present) {
                        $tq->where('locale', $present);
                    });
                });
            }

            return $query->orderBy('created_at', 'desc')->paginate(20);
        });

        if ($present) {
            $paginator->getCollection()->transform(function (BlogPost $post) use ($present) {
                TranslatableContentPresenter::applyBlogPost($post, $present);

                return $post;
            });
        }

        return $paginator;
    }

    public function store(Request $request)
    {
        $this->authorize('create', BlogPost::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:blog_posts,slug'],
            'excerpt' => ['nullable', 'string', 'max:1024'],
            'content' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:draft,published,archived'],
            'featured' => ['boolean'],
            'published_at' => ['nullable', 'date'],
            'content_locale' => ['nullable', 'string', 'max:16'],
            'translations' => ['nullable', 'array'],
            'translations.*.locale' => ['required', 'string', 'max:16'],
            'translations.*.title' => ['nullable', 'string', 'max:255'],
            'translations.*.excerpt' => ['nullable', 'string', 'max:1024'],
            'translations.*.content' => ['nullable', 'string'],
        ]);

        $translations = $data['translations'] ?? null;
        unset($data['translations']);

        $data['content_locale'] = SiteLanguages::normalizeContentLocale($data['content_locale'] ?? null);

        if (isset($data['content'])) {
            $data['content'] = $this->sanitizer->sanitize((string) $data['content']);
        }
        if (isset($data['excerpt'])) {
            $data['excerpt'] = $this->sanitizer->stripAll((string) $data['excerpt']);
        }

        $data['author_id'] = $request->user()->id;

        $post = BlogPost::create($data);

        if (is_array($translations)) {
            $this->syncBlogPostTranslations($post, $translations);
        }

        $post->load('author:id,name,email', 'translations');

        return response()->json($post, 201);
    }

    public function show(BlogPost $blogPost)
    {
        $this->authorize('view', $blogPost);

        $blogPost->load('author:id,name,email', 'seoMeta', 'translations');

        return response()->json($blogPost);
    }

    public function update(Request $request, BlogPost $blogPost)
    {
        $this->authorize('update', $blogPost);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', \Illuminate\Validation\Rule::unique('blog_posts')->ignore($blogPost->id)],
            'excerpt' => ['nullable', 'string', 'max:1024'],
            'content' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', 'string', 'in:draft,published,archived'],
            'featured' => ['boolean'],
            'published_at' => ['nullable', 'date'],
            'content_locale' => ['nullable', 'string', 'max:16'],
            'translations' => ['nullable', 'array'],
            'translations.*.locale' => ['required', 'string', 'max:16'],
            'translations.*.title' => ['nullable', 'string', 'max:255'],
            'translations.*.excerpt' => ['nullable', 'string', 'max:1024'],
            'translations.*.content' => ['nullable', 'string'],
        ]);

        $translations = $data['translations'] ?? null;
        unset($data['translations']);

        if (array_key_exists('content_locale', $data)) {
            $data['content_locale'] = SiteLanguages::normalizeContentLocale($data['content_locale'] ?? null);
        }

        if (array_key_exists('content', $data) && $data['content'] !== null) {
            $data['content'] = $this->sanitizer->sanitize((string) $data['content']);
        }
        if (array_key_exists('excerpt', $data) && $data['excerpt'] !== null) {
            $data['excerpt'] = $this->sanitizer->stripAll((string) $data['excerpt']);
        }

        $blogPost->update($data);

        if (is_array($translations)) {
            $this->syncBlogPostTranslations($blogPost, $translations);
        }

        return response()->json($blogPost->load('author:id,name,email', 'translations'));
    }

    public function destroy(BlogPost $blogPost)
    {
        $this->authorize('delete', $blogPost);

        $blogPost->delete();

        return response()->noContent();
    }

    /**
     * @param  array<int, array<string, mixed>>  $translations
     */
    private function syncBlogPostTranslations(BlogPost $post, array $translations): void
    {
        $post->refresh();
        $allowed = array_flip(SiteLanguages::secondaryLocaleCodesForContent($post->content_locale));
        $post->translations()->whereNotIn('locale', array_keys($allowed))->delete();

        if ($allowed === []) {
            return;
        }

        foreach ($translations as $row) {
            if (! is_array($row)) {
                continue;
            }
            $locale = strtolower(trim((string) ($row['locale'] ?? '')));
            if ($locale === '' || ! isset($allowed[$locale])) {
                continue;
            }

            $title = isset($row['title']) ? trim((string) $row['title']) : '';
            $excerpt = isset($row['excerpt']) ? trim((string) $row['excerpt']) : '';
            $content = isset($row['content']) ? trim((string) $row['content']) : '';

            if ($title === '' && $excerpt === '' && $content === '') {
                $post->translations()->where('locale', $locale)->delete();

                continue;
            }

            $post->translations()->updateOrCreate(
                ['locale' => $locale],
                [
                    'title' => $title !== '' ? $title : null,
                    'excerpt' => $excerpt !== '' ? $this->sanitizer->stripAll($excerpt) : null,
                    'content' => $content !== '' ? $this->sanitizer->sanitize($content) : null,
                ]
            );
        }
    }
}
