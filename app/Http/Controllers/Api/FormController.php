<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\PreparesUniqueContentSlug;
use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Services\Forms\FormLayoutDocument;
use App\Support\SiteLanguages;
use App\Support\TranslatableContentPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class FormController extends Controller
{
    use PreparesUniqueContentSlug;

    private const LIST_CACHE_KEY = 'forms:list:v1:json';

    public function index(Request $request)
    {
        $this->authorize('viewAny', Form::class);

        $present = TranslatableContentPresenter::requestedPresentationLocale($request);

        return response()->json(
            Cache::remember(self::LIST_CACHE_KEY.':loc:'.($present ?? 'none'), 3600, function () use ($present) {
                $query = Form::query()
                    ->with('translations')
                    ->withCount('submissions')
                    ->orderByDesc('updated_at')
                    ->orderBy('id')
                    ->when($present, function ($query) use ($present) {
                        TranslatableContentPresenter::scopeQueryForPresentationLocale($query, $present);
                    });

                $forms = $query->get();

                if ($present) {
                    $forms = $forms
                        ->map(function (Form $form) use ($present) {
                            TranslatableContentPresenter::applyForm($form, $present);

                            return $form;
                        })
                        ->filter(function (Form $form) use ($present) {
                            return TranslatableContentPresenter::hasFormContentForLocale($form, $present);
                        })
                        ->values();
                }

                return $forms;
            })
        );
    }

    public function store(Request $request)
    {
        $this->authorize('create', Form::class);

        $this->mergeUniqueContentSlug($request, Form::class, 'title');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:forms,slug'],
            'status' => ['sometimes', 'string', Rule::in([Form::STATUS_DRAFT, Form::STATUS_PUBLISHED])],
            'published_at' => ['nullable', 'date'],
            'content_locale' => ['nullable', 'string', 'max:16'],
            'layout' => ['nullable', 'array'],
            'actions' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
            'translations' => ['nullable', 'array'],
            'translations.*.locale' => ['required', 'string', 'max:16'],
            'translations.*.title' => ['nullable', 'string', 'max:255'],
        ]);

        $translations = $data['translations'] ?? null;
        unset($data['translations']);
        $data['content_locale'] = SiteLanguages::normalizeContentLocale($data['content_locale'] ?? null);
        $data['status'] = $data['status'] ?? Form::STATUS_DRAFT;
        $data['layout'] = FormLayoutDocument::normalizeLayout($data['layout'] ?? []);
        $data['actions'] = FormLayoutDocument::normalizeActions($data['actions'] ?? null);
        $data['settings'] = FormLayoutDocument::normalizeSettings($data['settings'] ?? []);
        if ($data['status'] === Form::STATUS_PUBLISHED && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $form = Form::create($data);
        if (is_array($translations)) {
            $this->syncFormTranslations($form, $translations);
        }
        $form->load(['translations']);
        $this->bumpFormCaches();

        return response()->json($form, 201);
    }

    public function show(Form $form)
    {
        $this->authorize('view', $form);
        $form->load(['translations']);
        $form->loadCount('submissions');

        return response()->json($form);
    }

    public function update(Request $request, Form $form)
    {
        $this->authorize('update', $form);

        $this->mergeUniqueContentSlug($request, Form::class, 'title', (int) $form->id, true);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('forms')->ignore($form->id)],
            'status' => ['sometimes', 'string', Rule::in([Form::STATUS_DRAFT, Form::STATUS_PUBLISHED])],
            'published_at' => ['nullable', 'date'],
            'content_locale' => ['nullable', 'string', 'max:16'],
            'layout' => ['nullable', 'array'],
            'actions' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
            'translations' => ['nullable', 'array'],
            'translations.*.locale' => ['required', 'string', 'max:16'],
            'translations.*.title' => ['nullable', 'string', 'max:255'],
        ]);

        $translations = $data['translations'] ?? null;
        unset($data['translations']);
        if (array_key_exists('content_locale', $data)) {
            $data['content_locale'] = SiteLanguages::normalizeContentLocale($data['content_locale'] ?? null);
        }
        if (array_key_exists('layout', $data)) {
            $data['layout'] = FormLayoutDocument::normalizeLayout($data['layout']);
        }
        if (array_key_exists('actions', $data)) {
            $data['actions'] = FormLayoutDocument::normalizeActions($data['actions']);
        }
        if (array_key_exists('settings', $data)) {
            $data['settings'] = FormLayoutDocument::normalizeSettings($data['settings']);
        }
        if (($data['status'] ?? $form->status) === Form::STATUS_PUBLISHED
            && empty($data['published_at'])
            && $form->published_at === null) {
            $data['published_at'] = now();
        }

        $form->update($data);
        if (is_array($translations)) {
            $this->syncFormTranslations($form, $translations);
        }
        $form->load(['translations']);
        $form->loadCount('submissions');
        $this->bumpFormCaches();

        return response()->json($form);
    }

    public function destroy(Form $form)
    {
        $this->authorize('delete', $form);
        $form->delete();
        $this->bumpFormCaches();

        return response()->noContent();
    }

    public function bulkDelete(Request $request)
    {
        $this->authorize('bulkDelete', Form::class);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:forms,id'],
        ]);

        $count = Form::whereIn('id', $data['ids'])->delete();
        $this->bumpFormCaches();

        return response()->json([
            'deleted' => $count,
            'message' => 'Deleted '.$count.' forms',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $translations
     */
    private function syncFormTranslations(Form $form, array $translations): void
    {
        $enabled = [];
        foreach (SiteLanguages::all() as $row) {
            $code = strtolower(trim((string) ($row['code'] ?? '')));
            if ($code !== '') {
                $enabled[$code] = true;
            }
        }
        $primary = SiteLanguages::primaryLocaleForContent($form->content_locale);
        $keep = [];

        foreach ($translations as $row) {
            if (! is_array($row)) {
                continue;
            }
            $locale = strtolower(trim((string) ($row['locale'] ?? '')));
            if ($locale === '' || $locale === $primary || ! isset($enabled[$locale])) {
                continue;
            }
            $form->translations()->updateOrCreate(
                ['locale' => $locale],
                ['title' => isset($row['title']) ? trim((string) $row['title']) : null]
            );
            $keep[] = $locale;
        }

        $form->translations()
            ->whereNotIn('locale', $keep)
            ->delete();
    }

    private function bumpFormCaches(): void
    {
        Form::bumpPublicCacheVersion();
        Cache::forget(self::LIST_CACHE_KEY.':loc:none');
        foreach (SiteLanguages::all() as $row) {
            $code = strtolower(trim((string) ($row['code'] ?? '')));
            if ($code !== '') {
                Cache::forget(self::LIST_CACHE_KEY.':loc:'.$code);
            }
        }
    }
}
