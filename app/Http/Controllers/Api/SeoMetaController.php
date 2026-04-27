<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SeoMeta;
use App\Services\ModelResolverService;
use Illuminate\Http\Request;

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

        $data = $request->validate([
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:512'],
            'canonical_url' => ['nullable', 'url'],
            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string', 'max:512'],
            'og_image' => ['nullable', 'url'],
            'twitter_card' => ['nullable', 'string', 'max:50'],
            'schema' => ['nullable', 'array'],
        ]);

        $meta = $model->seoMeta()->updateOrCreate([], $data);

        return response()->json($meta);
    }
}
