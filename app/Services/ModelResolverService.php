<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Project;
use App\Models\Service;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class ModelResolverService
{
    /**
     * Type to model class mapping.
     */
    protected array $typeMap = [
        'project' => Project::class,
        'category' => Category::class,
        'skill' => Skill::class,
        'service' => Service::class,
        'blog-post' => BlogPost::class,
    ];

    /**
     * Resolve model type string to full class name.
     */
    public function getModelType(string $type): string
    {
        $cacheKey = "model_type:{$type}";
        
        return Cache::remember($cacheKey, 3600, function () use ($type) {
            if (!isset($this->typeMap[$type])) {
                throw ValidationException::withMessages(['type' => 'Unsupported model type: ' . $type]);
            }
            
            return $this->typeMap[$type];
        });
    }

    /**
     * Resolve a model instance by type and ID.
     */
    public function resolveModel(string $type, int $id): Model
    {
        $modelClass = $this->getModelType($type);
        
        $model = $modelClass::find($id);
        
        if (!$model) {
            throw ValidationException::withMessages(['id' => ucfirst($type) . ' not found']);
        }
        
        return $model;
    }

    /**
     * Get all supported model types.
     */
    public function getSupportedTypes(): array
    {
        return array_keys($this->typeMap);
    }

    /**
     * Register a new model type mapping.
     */
    public function registerType(string $type, string $modelClass): void
    {
        if (!is_subclass_of($modelClass, Model::class)) {
            throw new \InvalidArgumentException("{$modelClass} must extend " . Model::class);
        }
        
        $this->typeMap[$type] = $modelClass;
        
        // Clear cache for this type
        Cache::forget("model_type:{$type}");
    }
}
