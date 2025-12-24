<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\MediaLibrary;
use App\Models\Project;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaController extends Controller
{
    /**
     * Upload media to the general media library (WordPress-style).
     */
    public function upload(Request $request)
    {
        $maxKb = (int) (env('UPLOAD_MAX_SIZE', 1024 * 1024 * 100) / 1024);

        $data = $request->validate([
            'file' => [
                'required',
                'file',
                "max:{$maxKb}",
                'mimes:jpeg,jpg,png,webp,avif,gif,svg,mp4,webm,mov,pdf',
            ],
        ]);

        /** @var UploadedFile $file */
        $file = $data['file'];

        $mediaLibrary = MediaLibrary::instance();
        $media = $mediaLibrary
            ->addMedia($file)
            ->toMediaCollection('default');

        return response()->json([
            'id' => $media->id,
            'file_name' => $media->file_name,
            'name' => $media->name,
            'collection' => $media->collection_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'url' => $media->getUrl(),
            'model_type' => $media->model_type,
            'model_id' => $media->model_id,
            'created_at' => $media->created_at,
        ], 201);
    }

    /**
    * Upload media to a specific collection of a model.
    */
    public function store(Request $request, string $type, int $id, string $collection)
    {
        $model = $this->resolveModel($type, $id);

        $this->ensureCollectionAllowed($model, $collection);

        $maxKb = (int) (env('UPLOAD_MAX_SIZE', 1024 * 1024 * 100) / 1024);

        $data = $request->validate([
            'file' => [
                'required',
                'file',
                "max:{$maxKb}",
                'mimes:jpeg,jpg,png,webp,avif,gif,svg,mp4,webm,mov,pdf',
            ],
        ]);

        /** @var UploadedFile $file */
        $file = $data['file'];

        $media = $model
            ->addMedia($file)
            ->toMediaCollection($collection);

        return response()->json([
            'id' => $media->id,
            'file_name' => $media->file_name,
            'collection' => $collection,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'url' => $media->getUrl(),
        ], 201);
    }

    /**
     * List all media items with optional filters.
     */
    public function index(Request $request)
    {
        $query = Media::query();

        if ($request->has('type')) {
            $query->where('model_type', $this->getModelType($request->type));
        }

        if ($request->has('collection')) {
            $query->where('collection_name', $request->collection);
        }

        if ($request->has('mime_type')) {
            $query->where('mime_type', 'like', $request->mime_type . '%');
        }

        $media = $query
            ->with('model:id')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        $media->getCollection()->transform(function ($item) {
            return [
                'id' => $item->id,
                'file_name' => $item->file_name,
                'name' => $item->name,
                'collection' => $item->collection_name,
                'mime_type' => $item->mime_type,
                'size' => $item->size,
                'url' => $item->getUrl(),
                'model_type' => $item->model_type,
                'model_id' => $item->model_id,
                'created_at' => $item->created_at,
            ];
        });

        return response()->json($media);
    }

    /**
     * Delete a media item.
     */
    public function destroy(Media $media)
    {
        $media->delete();

        return response()->noContent();
    }

    protected function getModelType(string $type): string
    {
        return match ($type) {
            'project' => Project::class,
            'category' => Category::class,
            'skill' => Skill::class,
            'blog-post' => BlogPost::class,
            default => throw ValidationException::withMessages(['type' => 'نوع غير مدعوم']),
        };
    }

    protected function resolveModel(string $type, int $id): Model
    {
        return match ($type) {
            'project' => Project::findOrFail($id),
            'category' => Category::findOrFail($id),
            'skill' => Skill::findOrFail($id),
            'blog-post' => \App\Models\BlogPost::findOrFail($id),
            default => throw ValidationException::withMessages(['type' => 'نوع غير مدعوم']),
        };
    }

    protected function ensureCollectionAllowed(Model $model, string $collection): void
    {
        $allowed = match (get_class($model)) {
            Project::class => ['hero', 'gallery', 'attachments', 'video'],
            Category::class => ['icon', 'thumb', 'banner'],
            Skill::class => ['icon'],
            \App\Models\BlogPost::class => ['featured_image', 'gallery'],
            default => [],
        };

        if (! in_array($collection, $allowed, true)) {
            throw ValidationException::withMessages(['collection' => 'غير مسموح بهذه المجموعة']);
        }
    }
}

