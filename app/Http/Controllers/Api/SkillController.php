<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use App\Support\SiteLanguages;
use App\Support\TranslatableContentPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class SkillController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Skill::class);

        $present = TranslatableContentPresenter::requestedPresentationLocale($request);
        $siteDefaultLocale = SiteLanguages::defaultCode();

        return response()->json(
            Cache::remember('skills:list:loc:'.($present ?? 'none'), 3600, function () use ($present, $siteDefaultLocale) {
                $skills = Skill::withCount('projects')
                    ->with('media')
                    ->with('translations')
                    ->when($present, function ($query) use ($present, $siteDefaultLocale) {
                        $query->where(function ($q) use ($present, $siteDefaultLocale) {
                            $q->where('content_locale', $present);
                            if ($present === $siteDefaultLocale) {
                                $q->orWhereNull('content_locale');
                            }
                            $q->orWhereHas('translations', function ($tq) use ($present) {
                                $tq->where('locale', $present);
                            });
                        });
                    })
                    ->orderBy('name')
                    ->get();

                if ($present) {
                    $skills->transform(function (Skill $skill) use ($present) {
                        TranslatableContentPresenter::applySkill($skill, $present);

                        return $skill;
                    });
                }

                return $skills;
            })
        );
    }

    public function store(Request $request)
    {
        $this->authorize('create', Skill::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:skills,slug'],
            'description' => ['nullable', 'string', 'max:1024'],
            'icon_hint' => ['nullable', 'string', 'max:255'],
            'content_locale' => ['nullable', 'string', 'max:16'],
            'translations' => ['nullable', 'array'],
            'translations.*.locale' => ['required', 'string', 'max:16'],
            'translations.*.name' => ['nullable', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string', 'max:1024'],
        ]);

        $translations = $data['translations'] ?? null;
        unset($data['translations']);
        $data['content_locale'] = SiteLanguages::normalizeContentLocale($data['content_locale'] ?? null);

        $skill = Skill::create($data);
        if (is_array($translations)) {
            $this->syncSkillTranslations($skill, $translations);
        }
        // Cache invalidation handled by InvalidatesCache trait

        return response()->json($skill, 201);
    }

    public function show(Skill $skill)
    {
        $this->authorize('view', $skill);

        $skill->load([
            'projects:id,title',
            'translations',
            'media' => function ($query) {
                $query->where('collection_name', 'icon');
            },
        ]);

        return response()->json($skill);
    }

    public function update(Request $request, Skill $skill)
    {
        $this->authorize('update', $skill);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('skills')->ignore($skill->id)],
            'description' => ['nullable', 'string', 'max:1024'],
            'icon_hint' => ['nullable', 'string', 'max:255'],
            'content_locale' => ['nullable', 'string', 'max:16'],
            'translations' => ['nullable', 'array'],
            'translations.*.locale' => ['required', 'string', 'max:16'],
            'translations.*.name' => ['nullable', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string', 'max:1024'],
        ]);

        $translations = $data['translations'] ?? null;
        unset($data['translations']);
        if (array_key_exists('content_locale', $data)) {
            $data['content_locale'] = SiteLanguages::normalizeContentLocale($data['content_locale'] ?? null);
        }

        $skill->update($data);
        if (is_array($translations)) {
            $this->syncSkillTranslations($skill, $translations);
        }
        // Cache invalidation handled by InvalidatesCache trait

        return response()->json($skill);
    }

    public function destroy(Skill $skill)
    {
        $this->authorize('delete', $skill);

        $skill->delete();
        // Cache invalidation handled by InvalidatesCache trait

        return response()->noContent();
    }

    public function bulkDelete(Request $request)
    {
        $this->authorize('bulkDelete', Skill::class);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:skills,id'],
        ]);

        $count = Skill::whereIn('id', $data['ids'])->delete();
        // Cache invalidation handled by InvalidatesCache trait on model events

        return response()->json([
            'deleted' => $count,
            'message' => 'Deleted '.$count.' skills',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $translations
     */
    private function syncSkillTranslations(Skill $skill, array $translations): void
    {
        $skill->refresh();
        $allowed = array_flip(SiteLanguages::secondaryLocaleCodesForContent($skill->content_locale));
        $skill->translations()->whereNotIn('locale', array_keys($allowed))->delete();
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
            $name = isset($row['name']) ? trim((string) $row['name']) : '';
            $description = isset($row['description']) ? trim((string) $row['description']) : '';
            if ($name === '' && $description === '') {
                $skill->translations()->where('locale', $locale)->delete();

                continue;
            }
            $skill->translations()->updateOrCreate(
                ['locale' => $locale],
                [
                    'name' => $name !== '' ? $name : null,
                    'description' => $description !== '' ? $description : null,
                ]
            );
        }
    }
}
