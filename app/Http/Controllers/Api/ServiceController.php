<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\HtmlSanitizerService;
use App\Support\SiteLanguages;
use App\Support\TranslatableContentPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    public function __construct(
        protected HtmlSanitizerService $sanitizer
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Service::class);

        $present = TranslatableContentPresenter::requestedPresentationLocale($request);

        return response()->json(
            Cache::remember('services:list:v3:loc:'.($present ?? 'none'), 3600, function () use ($present) {
                $query = Service::query()
                    ->with('translations')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->when($present, function ($query) use ($present) {
                        TranslatableContentPresenter::scopeQueryForPresentationLocale($query, $present);
                    });

                $services = $query->get();

                if ($present) {
                    $services = $services
                        ->map(function (Service $service) use ($present) {
                            TranslatableContentPresenter::applyService($service, $present);

                            return $service;
                        })
                        ->filter(function (Service $service) use ($present) {
                            return TranslatableContentPresenter::hasServiceContentForLocale($service, $present);
                        })
                        ->values();
                }

                return $services;
            })
        );
    }

    public function store(Request $request)
    {
        $this->authorize('create', Service::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:services,slug'],
            'short_description' => ['nullable', 'string', 'max:512'],
            'description' => ['nullable', 'string'],
            'process' => ['nullable', 'array'],
            'process.*' => ['string', 'max:500'],
            'deliverables' => ['nullable', 'array'],
            'deliverables.*' => ['string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:64'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_published' => ['sometimes', 'boolean'],
            'content_locale' => ['nullable', 'string', 'max:16'],
            'translations' => ['nullable', 'array'],
            'translations.*.locale' => ['required', 'string', 'max:16'],
            'translations.*.name' => ['nullable', 'string', 'max:255'],
            'translations.*.short_description' => ['nullable', 'string', 'max:512'],
            'translations.*.description' => ['nullable', 'string'],
            'translations.*.process' => ['nullable', 'array'],
            'translations.*.process.*' => ['string', 'max:500'],
            'translations.*.deliverables' => ['nullable', 'array'],
            'translations.*.deliverables.*' => ['string', 'max:500'],
        ]);

        $translations = $data['translations'] ?? null;
        unset($data['translations']);
        $data['content_locale'] = SiteLanguages::normalizeContentLocale($data['content_locale'] ?? null);
        if (isset($data['description'])) {
            $data['description'] = $this->sanitizer->stripAll($data['description']);
        }
        if (! array_key_exists('sort_order', $data) || $data['sort_order'] === null) {
            $data['sort_order'] = (int) (Service::query()->max('sort_order') ?? 0) + 1;
        }

        $service = Service::create($data);
        if (is_array($translations)) {
            $this->syncServiceTranslations($service, $translations);
        }
        $service->load('translations');
        $this->bumpServiceCaches();

        return response()->json($service, 201);
    }

    public function show(Service $service)
    {
        $this->authorize('view', $service);

        $service->load(['translations', 'seoMetas']);

        return response()->json($service);
    }

    public function update(Request $request, Service $service)
    {
        $this->authorize('update', $service);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('services')->ignore($service->id)],
            'short_description' => ['nullable', 'string', 'max:512'],
            'description' => ['nullable', 'string'],
            'process' => ['nullable', 'array'],
            'process.*' => ['string', 'max:500'],
            'deliverables' => ['nullable', 'array'],
            'deliverables.*' => ['string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:64'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_published' => ['sometimes', 'boolean'],
            'content_locale' => ['nullable', 'string', 'max:16'],
            'translations' => ['nullable', 'array'],
            'translations.*.locale' => ['required', 'string', 'max:16'],
            'translations.*.name' => ['nullable', 'string', 'max:255'],
            'translations.*.short_description' => ['nullable', 'string', 'max:512'],
            'translations.*.description' => ['nullable', 'string'],
            'translations.*.process' => ['nullable', 'array'],
            'translations.*.process.*' => ['string', 'max:500'],
            'translations.*.deliverables' => ['nullable', 'array'],
            'translations.*.deliverables.*' => ['string', 'max:500'],
        ]);

        $translations = $data['translations'] ?? null;
        unset($data['translations']);
        if (array_key_exists('content_locale', $data)) {
            $data['content_locale'] = SiteLanguages::normalizeContentLocale($data['content_locale'] ?? null);
        }
        if (isset($data['description'])) {
            $data['description'] = $this->sanitizer->stripAll($data['description']);
        }

        $service->update($data);
        if (is_array($translations)) {
            $this->syncServiceTranslations($service, $translations);
        }
        $service->load('translations');
        $this->bumpServiceCaches();

        return response()->json($service);
    }

    public function destroy(Service $service)
    {
        $this->authorize('delete', $service);
        $service->delete();
        $this->bumpServiceCaches();

        return response()->noContent();
    }

    public function bulkDelete(Request $request)
    {
        $this->authorize('bulkDelete', Service::class);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:services,id'],
        ]);

        $count = Service::whereIn('id', $data['ids'])->delete();
        $this->bumpServiceCaches();

        return response()->json([
            'deleted' => $count,
            'message' => 'Deleted '.$count.' services',
        ]);
    }

    private function bumpServiceCaches(): void
    {
        Cache::forget('services:list:loc:none');
        Cache::forget('public:services:loc:none');
        foreach (SiteLanguages::all() as $row) {
            $code = isset($row['code']) ? strtolower(trim((string) $row['code'])) : '';
            if ($code === '') {
                continue;
            }
            Cache::forget('services:list:loc:'.$code);
            Cache::forget('public:services:loc:'.$code);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $translations
     */
    private function syncServiceTranslations(Service $service, array $translations): void
    {
        $service->refresh();
        $secondaryCodes = SiteLanguages::secondaryLocaleCodesForContent($service->content_locale);
        if ($secondaryCodes === []) {
            return;
        }
        $allowed = array_flip($secondaryCodes);
        $service->translations()->whereNotIn('locale', array_keys($allowed))->delete();
        foreach ($translations as $row) {
            if (! is_array($row)) {
                continue;
            }
            $locale = strtolower(trim((string) ($row['locale'] ?? '')));
            if ($locale === '' || ! isset($allowed[$locale])) {
                continue;
            }
            $name = isset($row['name']) ? trim((string) $row['name']) : '';
            $shortDescription = isset($row['short_description']) ? trim((string) $row['short_description']) : '';
            $description = isset($row['description']) ? $this->sanitizer->stripAll((string) $row['description']) : '';
            $process = $this->normalizeStringList($row['process'] ?? null);
            $deliverables = $this->normalizeStringList($row['deliverables'] ?? null);

            if ($name === '' && $shortDescription === '' && $description === '' && $process === [] && $deliverables === []) {
                $service->translations()->where('locale', $locale)->delete();

                continue;
            }
            $service->translations()->updateOrCreate(
                ['locale' => $locale],
                [
                    'name' => $name !== '' ? $name : null,
                    'short_description' => $shortDescription !== '' ? $shortDescription : null,
                    'description' => $description !== '' ? $description : null,
                    'process' => $process !== [] ? $process : null,
                    'deliverables' => $deliverables !== [] ? $deliverables : null,
                ]
            );
        }
    }

    /**
     * @param  mixed  $raw
     * @return list<string>
     */
    private function normalizeStringList($raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            $s = is_string($item) ? trim($item) : '';
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return $out;
    }
}
