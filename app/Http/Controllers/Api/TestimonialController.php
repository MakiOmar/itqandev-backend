<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use App\Services\HtmlSanitizerService;
use App\Support\SiteLanguages;
use App\Support\TranslatableContentPresenter;
use Illuminate\Http\Request;

class TestimonialController extends Controller
{
    public function __construct(
        protected HtmlSanitizerService $sanitizer
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Testimonial::class);

        $present = TranslatableContentPresenter::requestedPresentationLocale($request);

        $query = Testimonial::with('project:id,title,content_locale', 'translations');

        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->has('approved')) {
            $query->where('approved', $request->boolean('approved'));
        }

        $paginator = $query->orderBy('created_at', 'desc')->paginate(20);

        if ($present) {
            $paginator->getCollection()->transform(function (Testimonial $t) use ($present) {
                TranslatableContentPresenter::applyTestimonial($t, $present);
                if ($t->relationLoaded('project') && $t->project !== null) {
                    TranslatableContentPresenter::applyProject($t->project, $present);
                }

                return $t;
            });
        }

        return $paginator;
    }

    public function store(Request $request)
    {
        $this->authorize('create', Testimonial::class);

        $this->mergeCamelCaseFields($request);

        $data = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'content_locale' => ['nullable', 'string', 'max:16'],
            'client_name' => ['required', 'string', 'max:255'],
            'client_role' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'content' => ['required', 'string'],
            'video_url' => ['nullable', 'url'],
            'approved' => ['boolean'],
            'translations' => ['nullable', 'array'],
            'translations.*.locale' => ['required', 'string', 'max:16'],
            'translations.*.content' => ['nullable', 'string'],
            'translations.*.client_role' => ['nullable', 'string', 'max:255'],
            'translations.*.company' => ['nullable', 'string', 'max:255'],
        ]);

        $translations = $data['translations'] ?? null;
        unset($data['translations']);

        $data['content_locale'] = SiteLanguages::normalizeContentLocale($data['content_locale'] ?? null);
        $data['content'] = $this->sanitizer->stripAll((string) $data['content']);
        if (isset($data['client_role'])) {
            $data['client_role'] = $this->sanitizer->stripAll((string) $data['client_role']);
        }
        if (isset($data['company'])) {
            $data['company'] = $this->sanitizer->stripAll((string) $data['company']);
        }

        $testimonial = Testimonial::create($data);

        if (is_array($translations)) {
            $this->syncTestimonialTranslations($testimonial, $translations);
        }

        return response()->json($testimonial->load('project:id,title,content_locale', 'translations'), 201);
    }

    public function show(Testimonial $testimonial)
    {
        $this->authorize('view', $testimonial);

        $present = TranslatableContentPresenter::requestedPresentationLocale(request());
        $testimonial->load('project:id,title,content_locale', 'translations');
        if ($present) {
            TranslatableContentPresenter::applyTestimonial($testimonial, $present);
            if ($testimonial->project !== null) {
                TranslatableContentPresenter::applyProject($testimonial->project, $present);
            }
        }

        return response()->json($testimonial);
    }

    public function update(Request $request, Testimonial $testimonial)
    {
        $this->authorize('update', $testimonial);

        $this->mergeCamelCaseFields($request);

        $data = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'content_locale' => ['nullable', 'string', 'max:16'],
            'client_name' => ['sometimes', 'required', 'string', 'max:255'],
            'client_role' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'rating' => ['sometimes', 'required', 'integer', 'min:1', 'max:5'],
            'content' => ['sometimes', 'required', 'string'],
            'video_url' => ['nullable', 'url'],
            'approved' => ['boolean'],
            'translations' => ['nullable', 'array'],
            'translations.*.locale' => ['required', 'string', 'max:16'],
            'translations.*.content' => ['nullable', 'string'],
            'translations.*.client_role' => ['nullable', 'string', 'max:255'],
            'translations.*.company' => ['nullable', 'string', 'max:255'],
        ]);

        $translations = $data['translations'] ?? null;
        unset($data['translations']);

        if (array_key_exists('content_locale', $data)) {
            $data['content_locale'] = SiteLanguages::normalizeContentLocale($data['content_locale'] ?? null);
        }

        if (array_key_exists('content', $data)) {
            $data['content'] = $this->sanitizer->stripAll((string) $data['content']);
        }
        if (array_key_exists('client_role', $data) && $data['client_role'] !== null) {
            $data['client_role'] = $this->sanitizer->stripAll((string) $data['client_role']);
        }
        if (array_key_exists('company', $data) && $data['company'] !== null) {
            $data['company'] = $this->sanitizer->stripAll((string) $data['company']);
        }

        $testimonial->update($data);

        if (is_array($translations)) {
            $this->syncTestimonialTranslations($testimonial, $translations);
        }

        return response()->json($testimonial->load('project:id,title,content_locale', 'translations'));
    }

    public function destroy(Testimonial $testimonial)
    {
        $this->authorize('delete', $testimonial);

        $testimonial->delete();

        return response()->json(null, 204);
    }

    public function bulkDelete(Request $request)
    {
        $this->authorize('bulkDelete', Testimonial::class);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:testimonials,id'],
        ]);

        $count = Testimonial::whereIn('id', $data['ids'])->delete();

        return response()->json([
            'deleted' => $count,
            'message' => 'Deleted '.$count.' testimonials',
        ]);
    }

    private function mergeCamelCaseFields(Request $request): void
    {
        $request->merge([
            'project_id' => $request->input('project_id') ?? $request->input('projectId'),
            'content_locale' => $request->input('content_locale') ?? $request->input('contentLocale'),
            'client_name' => $request->input('client_name') ?? $request->input('clientName'),
            'client_role' => $request->input('client_role') ?? $request->input('clientRole'),
            'company' => $request->input('company'),
            'rating' => $request->input('rating'),
            'content' => $request->input('content'),
            'video_url' => $request->input('video_url') ?? $request->input('videoUrl'),
            'approved' => $request->input('approved'),
        ]);

        $raw = $request->input('translations');
        if (! is_array($raw)) {
            return;
        }

        $normalized = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $normalized[] = [
                'locale' => isset($row['locale']) ? strtolower(trim((string) $row['locale'])) : '',
                'content' => $row['content'] ?? null,
                'client_role' => $row['client_role'] ?? $row['clientRole'] ?? null,
                'company' => $row['company'] ?? null,
            ];
        }
        $request->merge(['translations' => $normalized]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $translations
     */
    private function syncTestimonialTranslations(Testimonial $testimonial, array $translations): void
    {
        $testimonial->refresh();
        $allowed = array_flip(SiteLanguages::secondaryLocaleCodesForContent($testimonial->content_locale));
        $testimonial->translations()->whereNotIn('locale', array_keys($allowed))->delete();

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

            $content = isset($row['content']) ? trim((string) $row['content']) : '';
            $clientRole = isset($row['client_role']) ? trim((string) $row['client_role']) : '';
            $company = isset($row['company']) ? trim((string) $row['company']) : '';

            if ($content === '' && $clientRole === '' && $company === '') {
                $testimonial->translations()->where('locale', $locale)->delete();

                continue;
            }

            $testimonial->translations()->updateOrCreate(
                ['locale' => $locale],
                [
                    'content' => $content !== '' ? $this->sanitizer->stripAll($content) : null,
                    'client_role' => $clientRole !== '' ? $this->sanitizer->stripAll($clientRole) : null,
                    'company' => $company !== '' ? $this->sanitizer->stripAll($company) : null,
                ]
            );
        }
    }
}
