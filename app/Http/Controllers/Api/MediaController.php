<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
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
    * Delete a media item.
    */
    public function destroy(Media $media)
    {
        $media->delete();

        return response()->noContent();
    }

    protected function resolveModel(string $type, int $id): Model
    {
        return match ($type) {
            'project' => Project::findOrFail($id),
            'category' => Category::findOrFail($id),
            'skill' => Skill::findOrFail($id),
            default => throw ValidationException::withMessages(['type' => 'نوع غير مدعوم']),
        };
    }

    protected function ensureCollectionAllowed(Model $model, string $collection): void
    {
        $allowed = match (get_class($model)) {
            Project::class => ['hero', 'gallery', 'attachments', 'video'],
            Category::class => ['icon', 'thumb', 'banner'],
            Skill::class => ['icon'],
            default => [],
        };

        if (! in_array($collection, $allowed, true)) {
            throw ValidationException::withMessages(['collection' => 'غير مسموح بهذه المجموعة']);
        }
    }
}

