<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SeoMeta;
use App\Services\ModelResolverService;
use App\Support\SiteLanguages;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SeoMetaController extends Controller
{
    protected ModelResolverService $modelResolver;

    public function __construct(ModelResolverService $modelResolver)
    {
        $this->modelResolver = $modelResolver;
    }

    public function update(Request $request, string $type, int $id)
    {
        $this->authorize('update', new SeoMeta);

        $model = $this->modelResolver->resolveModel($type, $id);

        $enabledLocales = array_values(array_map(static fn (array $row) => $row['code'], SiteLanguages::all()));

        $data = $request->validate([
            'locale' => ['required', 'string', 'max:16', Rule::in($enabledLocales)],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:512'],
            'canonical_url' => ['nullable', 'url'],
            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string', 'max:512'],
            'og_image' => ['nullable', 'url'],
            'twitter_card' => ['nullable', 'string', 'max:50'],
            'schema' => ['nullable', 'array'],
        ]);

        $locale = strtolower(trim((string) ($data['locale'] ?? '')));

        $meta = $model->seoMetas()->updateOrCreate(
            ['locale' => $locale],
            array_merge($data, ['locale' => $locale])
        );

        return response()->json($meta);
    }
}
