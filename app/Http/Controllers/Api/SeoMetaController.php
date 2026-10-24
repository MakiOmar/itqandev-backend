<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesResolvedModel;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSeoMetaRequest;
use App\Services\ModelResolverService;
use App\Support\PublicSeoCache;

class SeoMetaController extends Controller
{
    use AuthorizesResolvedModel;

    protected ModelResolverService $modelResolver;

    public function __construct(ModelResolverService $modelResolver)
    {
        $this->modelResolver = $modelResolver;
    }

    public function update(UpdateSeoMetaRequest $request, string $type, int $id)
    {
        $model = $this->modelResolver->resolveModel($type, $id);
        $this->authorizeParentUpdate($model);

        $data = $request->validated();

        $meta = $model->seoMetas()->updateOrCreate(
            ['locale' => $data['locale']],
            $data
        );

        PublicSeoCache::forget($model);

        return response()->json($meta);
    }
}
