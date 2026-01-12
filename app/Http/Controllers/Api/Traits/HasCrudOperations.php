<?php

namespace App\Http\Controllers\Api\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

trait HasCrudOperations
{
    /**
     * Get the model class name for this controller.
     */
    abstract protected function getModelClass(): string;

    /**
     * Get the cache key prefix for this model.
     */
    protected function getCacheKeyPrefix(): string
    {
        $modelClass = $this->getModelClass();
        $modelName = class_basename($modelClass);
        return strtolower($modelName) . 's';
    }

    /**
     * Get validation rules for store operation.
     */
    abstract protected function getStoreRules(): array;

    /**
     * Get validation rules for update operation.
     */
    abstract protected function getUpdateRules(Model $model): array;

    /**
     * Invalidate cache for this model.
     */
    protected function invalidateCache(): void
    {
        $prefix = $this->getCacheKeyPrefix();
        Cache::forget("{$prefix}:list");
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $modelClass = $this->getModelClass();
        $this->authorize('viewAny', $modelClass);

        $cacheKey = $this->getCacheKeyPrefix() . ':list';
        
        return response()->json(
            Cache::remember($cacheKey, 3600, function () use ($modelClass) {
                return $modelClass::query()
                    ->with($this->getEagerLoadRelations())
                    ->orderBy($this->getDefaultOrderColumn())
                    ->get();
            })
        );
    }

    /**
     * Get relationships to eager load.
     */
    protected function getEagerLoadRelations(): array
    {
        return [];
    }

    /**
     * Get default column for ordering.
     */
    protected function getDefaultOrderColumn(): string
    {
        return 'id';
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $modelClass = $this->getModelClass();
        $this->authorize('create', $modelClass);

        $data = $request->validate($this->getStoreRules());

        // Apply any data transformations
        $data = $this->transformStoreData($data);

        $model = $modelClass::create($data);

        $this->invalidateCache();

        return response()->json($model, 201);
    }

    /**
     * Transform data before storing.
     */
    protected function transformStoreData(array $data): array
    {
        return $data;
    }

    /**
     * Display the specified resource.
     */
    public function show(Model $model)
    {
        $this->authorize('view', $model);

        $model->load($this->getEagerLoadRelations());

        return response()->json($model);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Model $model)
    {
        $this->authorize('update', $model);

        $data = $request->validate($this->getUpdateRules($model));

        // Apply any data transformations
        $data = $this->transformUpdateData($data, $model);

        $model->update($data);

        $this->invalidateCache();

        return response()->json($model);
    }

    /**
     * Transform data before updating.
     */
    protected function transformUpdateData(array $data, Model $model): array
    {
        return $data;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Model $model)
    {
        $this->authorize('delete', $model);

        $model->delete();

        $this->invalidateCache();

        return response()->noContent();
    }
}
