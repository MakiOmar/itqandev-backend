<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\MediaFolder;
use App\Models\MediaLibrary;
use App\Models\MediaTag;
use App\Models\Project;
use App\Models\Skill;
use App\Services\MediaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\AppMedia as Media;

class MediaController extends Controller
{
    protected MediaService $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    /**
     * List all media items with optional filters.
     */
    public function index(Request $request)
    {
        $query = Media::query();

        // Filter by model type
        if ($request->has('type')) {
            $query->where('model_type', $this->getModelType($request->type));
        }

        // Filter by collection
        if ($request->has('collection')) {
            $query->where('collection_name', $request->collection);
        }

        // Filter by MIME type
        if ($request->has('mime_type')) {
            if ($request->mime_type === 'image') {
                $query->where('mime_type', 'like', 'image/%');
            } elseif ($request->mime_type === 'video') {
                $query->where('mime_type', 'like', 'video/%');
            } else {
                $query->where('mime_type', 'like', $request->mime_type . '%');
            }
        }

        // Filter by folder
        if ($request->has('folder_id')) {
            $query->where('folder_id', $request->folder_id);
        }

        // Search by name
        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('file_name', 'like', '%' . $request->search . '%');
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $allowedSorts = ['created_at', 'name', 'size', 'mime_type'];
        
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $media = $query
            ->with(['folder:id,name', 'tags:id,name'])
            ->paginate($request->get('per_page', 20));

        $media->getCollection()->transform(function ($item) {
            return $this->transformMedia($item);
        });

        return response()->json($media);
    }

    /**
     * Show a single media item.
     */
    public function show($id)
    {
        $media = Media::findOrFail($id);
        $media->load(['folder', 'tags', 'usages.usable']);

        return response()->json($this->transformMedia($media, true));
    }

    /**
     * Upload media to the general media library (WordPress-style).
     */
    public function upload(Request $request)
    {
        $maxKb = (int) (config('media.max_file_size', 104857600) / 1024);

        $data = $request->validate([
            'file' => [
                'required',
                'file',
                "max:{$maxKb}",
                'mimes:jpeg,jpg,png,webp,avif,gif,svg,mp4,webm,mov,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,mp3,wav,ogg,m4a,aac',
            ],
            'folder_id' => ['nullable', 'integer', 'exists:media_folders,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:255'],
        ]);

        /** @var UploadedFile $file */
        $file = $data['file'];
        $folderId = $data['folder_id'] ?? null;
        $tags = $data['tags'] ?? null;

        // Store file temporarily in request for MediaService
        $request->merge(['file' => $file]);

        $media = $this->mediaService->processUpload($file, $folderId, $tags);

        return response()->json($this->transformMedia($media), 201);
    }

    /**
     * Upload media to a specific collection of a model.
     */
    public function store(Request $request, string $type, int $id, string $collection)
    {
        $model = $this->resolveModel($type, $id);
        $this->ensureCollectionAllowed($model, $collection);

        $maxKb = (int) (config('media.max_file_size', 104857600) / 1024);

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

        // Track usage
        $this->mediaService->trackUsage($media, $model, $collection);

        return response()->json($this->transformMedia($media), 201);
    }

    /**
     * Update a media item.
     */
    public function update(Request $request, $id)
    {
        $media = Media::findOrFail($id);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'folder_id' => ['nullable', 'integer', 'exists:media_folders,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:255'],
        ]);

        if (isset($data['name'])) {
            $media->update(['name' => $data['name']]);
        }

        if (isset($data['alt_text'])) {
            $media->update(['alt_text' => $data['alt_text']]);
        }

        if (isset($data['description'])) {
            $media->update(['description' => $data['description']]);
        }

        if (isset($data['folder_id'])) {
            $this->mediaService->updateMediaFolder($media, $data['folder_id']);
        }

        if (isset($data['tags'])) {
            $this->mediaService->attachTags($media, $data['tags']);
        }

        $media->refresh();
        $media->load(['folder', 'tags']);

        return response()->json($this->transformMedia($media));
    }

    /**
     * Delete a media item.
     */
    public function destroy($id)
    {
        $media = Media::findOrFail($id);
        $this->mediaService->deleteMedia($media);

        return response()->noContent();
    }

    /**
     * Bulk delete media items.
     */
    public function bulkDelete(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:media,id'],
        ]);

        $mediaItems = Media::whereIn('id', $data['ids'])->get();

        foreach ($mediaItems as $media) {
            $this->mediaService->deleteMedia($media);
        }

        return response()->json([
            'message' => 'تم حذف ' . count($data['ids']) . ' ملف بنجاح',
            'deleted_count' => count($data['ids']),
        ]);
    }

    /**
     * Move media to a folder.
     */
    public function moveToFolder(Request $request)
    {
        $data = $request->validate([
            'media_ids' => ['required', 'array', 'min:1'],
            'media_ids.*' => ['integer', 'exists:media,id'],
            'folder_id' => ['nullable', 'integer', 'exists:media_folders,id'],
        ]);

        Media::whereIn('id', $data['media_ids'])
            ->update(['folder_id' => $data['folder_id']]);

        return response()->json([
            'message' => 'تم نقل الملفات بنجاح',
            'moved_count' => count($data['media_ids']),
        ]);
    }

    /**
     * Get media statistics.
     */
    public function statistics()
    {
        $total = Media::count();
        $images = Media::where('mime_type', 'like', 'image/%')->count();
        $videos = Media::where('mime_type', 'like', 'video/%')->count();
        $audio = Media::where('mime_type', 'like', 'audio/%')->count();
        $documents = $total - $images - $videos - $audio;

        $stats = [
            'total' => $total,
            'by_type' => [
                'images' => $images,
                'videos' => $videos,
                'audio' => $audio,
                'documents' => $documents,
            ],
            'total_size' => Media::sum('size'),
            'folders_count' => MediaFolder::count(),
            'tags_count' => MediaTag::count(),
        ];

        return response()->json($stats);
    }

    /**
     * Get all folders.
     */
    public function getFolders()
    {
        $folders = MediaFolder::with('children')
            ->whereNull('parent_id')
            ->orderBy('order')
            ->get();

        return response()->json($folders);
    }

    /**
     * Create a folder.
     */
    public function createFolder(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:media_folders,id'],
            'description' => ['nullable', 'string'],
        ]);

        $folder = MediaFolder::create($data);

        return response()->json($folder, 201);
    }

    /**
     * Update a folder.
     */
    public function updateFolder(Request $request, MediaFolder $folder)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:media_folders,id', 'not_in:' . $folder->id],
            'description' => ['nullable', 'string'],
            'order' => ['sometimes', 'integer'],
        ]);

        $folder->update($data);

        return response()->json($folder);
    }

    /**
     * Delete a folder.
     */
    public function deleteFolder(MediaFolder $folder)
    {
        // Move media to root (null folder)
        Media::where('folder_id', $folder->id)->update(['folder_id' => null]);

        // Delete folder
        $folder->delete();

        return response()->noContent();
    }

    /**
     * Transform media item for API response.
     */
    protected function transformMedia(Media $media, bool $detailed = false): array
    {
        $data = [
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
            'folder_id' => $media->folder_id ?? null,
            'alt_text' => $media->alt_text ?? null,
            'description' => $media->description ?? null,
        ];

        if ($media->relationLoaded('folder') && $media->folder) {
            $data['folder'] = [
                'id' => $media->folder->id,
                'name' => $media->folder->name,
                'slug' => $media->folder->slug,
            ];
        }

        if ($media->relationLoaded('tags')) {
            $data['tags'] = $media->tags->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                ];
            });
        }

        if ($detailed && $media->relationLoaded('usages')) {
            $data['usages'] = $media->usages->map(function ($usage) {
                return [
                    'id' => $usage->id,
                    'usable_type' => $usage->usable_type,
                    'usable_id' => $usage->usable_id,
                    'collection_name' => $usage->collection_name,
                ];
            });
        }

        return $data;
    }

    protected function getModelType(string $type): string
    {
        return match ($type) {
            'project' => Project::class,
            'category' => Category::class,
            'skill' => Skill::class,
            'blog-post' => BlogPost::class,
            'library' => MediaLibrary::class,
            default => throw ValidationException::withMessages(['type' => 'نوع غير مدعوم']),
        };
    }

    protected function resolveModel(string $type, int $id): Model
    {
        return match ($type) {
            'project' => Project::findOrFail($id),
            'category' => Category::findOrFail($id),
            'skill' => Skill::findOrFail($id),
            'blog-post' => BlogPost::findOrFail($id),
            default => throw ValidationException::withMessages(['type' => 'نوع غير مدعوم']),
        };
    }

    protected function ensureCollectionAllowed(Model $model, string $collection): void
    {
        $allowed = match (get_class($model)) {
            Project::class => ['hero', 'gallery', 'attachments', 'video'],
            Category::class => ['icon', 'thumb', 'banner'],
            Skill::class => ['icon'],
            BlogPost::class => ['featured_image', 'gallery'],
            default => [],
        };

        if (! in_array($collection, $allowed, true)) {
            throw ValidationException::withMessages(['collection' => 'غير مسموح بهذه المجموعة']);
        }
    }
}
