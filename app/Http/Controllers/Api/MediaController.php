<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\MediaFolder;
use App\Models\MediaLibrary;
use App\Models\MediaTag;
use App\Models\Project;
use App\Models\Skill;
use App\Services\MediaService;
use App\Services\MediaTransformerService;
use App\Services\ModelResolverService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Models\AppMedia as Media;
use ZipArchive;

class MediaController extends Controller
{
    protected MediaService $mediaService;
    protected ModelResolverService $modelResolver;

    public function __construct(MediaService $mediaService, ModelResolverService $modelResolver)
    {
        $this->mediaService = $mediaService;
        $this->modelResolver = $modelResolver;
    }

    /**
     * List all media items with optional filters.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Media::class);

        $query = Media::query();

        // Filter by model type
        if ($request->has('type')) {
            $query->where('model_type', $this->getModelType($request->type));
        }

        // Filter by model_type (direct)
        if ($request->has('model_type')) {
            $query->where('model_type', $request->model_type);
        }

        // Filter by model_id
        if ($request->has('model_id')) {
            $query->where('model_id', $request->model_id);
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
            } elseif ($request->mime_type === 'audio') {
                $query->where('mime_type', 'like', 'audio/%');
            } elseif ($request->mime_type === 'document') {
                // Filter for common document types (PDF, Word, Excel, text files, etc.)
                $query->where(function ($q) {
                    $q->where('mime_type', 'application/pdf')
                      ->orWhere('mime_type', 'like', 'application/msword%')
                      ->orWhere('mime_type', 'like', 'application/vnd.openxmlformats-officedocument%')
                      ->orWhere('mime_type', 'like', 'application/vnd.ms-excel%')
                      ->orWhere('mime_type', 'like', 'application/vnd.ms-powerpoint%')
                      ->orWhere('mime_type', 'like', 'text/%');
                });
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

        return MediaResource::collection($media);
    }

    /**
     * Show a single media item.
     */
    public function show($id)
    {
        $media = Media::findOrFail($id);
        $this->authorize('view', $media);
        
        $media->load(['folder', 'tags', 'usages.usable']);

        return new MediaResource($media);
    }

    /**
     * Upload media to the general media library (WordPress-style).
     */
    public function upload(Request $request)
    {
        try {
            $this->authorize('upload', Media::class);
        } catch (\Exception $authError) {
            throw $authError;
        }

        $maxKb = (int) (config('media.max_file_size', 104857600) / 1024);

        try {
            $data = $request->validate([
            'file' => [
                'required',
                'file',
                "max:{$maxKb}",
                'mimes:jpeg,jpg,png,webp,avif,gif,svg,mp4,webm,mov,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,mp3,wav,ogg,m4a,aac',
                function ($attribute, $value, $fail) {
                    if (!$value instanceof \Illuminate\Http\UploadedFile) {
                        $fail('Invalid file upload.');
                        return;
                    }
                    
                    // Additional MIME type verification will be done in MediaService
                    $allowedMimes = [
                        'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/avif',
                        'video/mp4', 'video/webm', 'video/quicktime', 'video/x-msvideo',
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-powerpoint',
                        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/m4a', 'audio/aac',
                        'text/plain', 'text/csv',
                    ];
                    
                    $mimeType = $value->getMimeType();
                    if (!in_array($mimeType, $allowedMimes, true)) {
                        $fail('File type ' . $mimeType . ' is not allowed.');
                    }
                },
            ],
            'folder_id' => ['nullable', 'integer', 'exists:media_folders,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:255'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $validationError) {
            throw $validationError;
        }

        /** @var UploadedFile $file */
        $file = $data['file'];
        $folderId = $data['folder_id'] ?? null;
        $tags = $data['tags'] ?? null;

        // Store file temporarily in request for MediaService
        $request->merge(['file' => $file]);

        try {
            $media = $this->mediaService->processUpload($file, $folderId, $tags);
            return (new MediaResource($media))->response()->setStatusCode(201);
        } catch (\Exception $processError) {
            throw $processError;
        }
    }

    /**
     * Upload media to a specific collection of a model.
     */
    public function store(Request $request, string $type, int $id, string $collection)
    {
        $model = $this->resolveModel($type, $id);
        $this->ensureCollectionAllowed($model, $collection);

        // Check if attaching existing media or uploading new file
        if ($request->has('media_id')) {
            return $this->attachExistingMedia($request, $model, $collection);
        }

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

        return (new MediaResource($media))->response()->setStatusCode(201);
    }

    /**
     * Attach existing media to a model.
     */
    protected function attachExistingMedia(Request $request, Model $model, string $collection)
    {
        $data = $request->validate([
            'media_id' => ['required', 'integer', 'exists:media,id'],
        ]);

        $sourceMedia = Media::findOrFail($data['media_id']);

        // Check if media already exists in this collection for this model
        // For single-file collections (hero, video), delete existing media before attaching new one
        // IMPORTANT: Use getMedia($collection) which filters by model_id and model_type
        $existingMedia = $model->getMedia($collection)->first();
        
        // Additional safety: Query database directly to ensure media belongs to this model
        if ($existingMedia) {
            $directQuery = Media::where('model_type', get_class($model))
                ->where('model_id', $model->id)
                ->where('collection_name', $collection)
                ->first();
            
            // Only proceed if direct query confirms ownership
            if (!$directQuery || $directQuery->id !== $existingMedia->id) {
                $existingMedia = null; // Don't delete if ownership doesn't match
            }
        }
        
        // Double-check that the media actually belongs to this model
        if ($existingMedia && $existingMedia->model_id == $model->id && $existingMedia->model_type == get_class($model)) {
            $existingMedia->delete();
            
            // Refresh model to clear any cached relationships
            $model->refresh();
        }

        // Get the file path from the source media
        $sourcePath = $sourceMedia->getPath();
        
        // Get the disk instance using Storage facade
        $disk = Storage::disk($sourceMedia->disk);
        $relativePath = $sourceMedia->getPath();
        
        // Try to get the file content directly from disk (most reliable method)
        try {
            if ($disk->exists($relativePath)) {
                // Get file content from disk
                $fileContent = $disk->get($relativePath);
                $tempPath = sys_get_temp_dir() . '/' . uniqid() . '_' . $sourceMedia->file_name;
                
                if (file_put_contents($tempPath, $fileContent) !== false) {
                    $newMedia = $model
                        ->addMedia($tempPath)
                        ->usingName($sourceMedia->name ?? $sourceMedia->file_name)
                        ->toMediaCollection($collection);
                    
                    // Refresh model relationships to clear any caching
                    $model->refresh();
                    $model->load('media');
                    
                    // Clean up temp file
                    @unlink($tempPath);
                } else {
                    throw ValidationException::withMessages(['media_id' => 'Failed to create temporary file']);
                }
            } elseif ($sourcePath && file_exists($sourcePath)) {
                // Fallback: try direct file path copy
                $tempPath = sys_get_temp_dir() . '/' . uniqid() . '_' . $sourceMedia->file_name;
                if (copy($sourcePath, $tempPath)) {
                    $newMedia = $model
                        ->addMedia($tempPath)
                        ->usingName($sourceMedia->name ?? $sourceMedia->file_name)
                        ->toMediaCollection($collection);
                    
                    // Refresh model relationships to clear any caching
                    $model->refresh();
                    $model->load('media');
                    
                    @unlink($tempPath);
                } else {
                    throw ValidationException::withMessages(['media_id' => 'Failed to copy source media file']);
                }
            } else {
                throw ValidationException::withMessages(['media_id' => 'Source media file not found']);
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw ValidationException::withMessages(['media_id' => 'Failed to attach media: ' . $e->getMessage()]);
        }

        // Track usage of the original media
        $this->mediaService->trackUsage($sourceMedia, $model, $collection);

        // Refresh model to ensure relationships are up to date
        $model->refresh();
        $model->load('media');
        $model->unsetRelation('media');

        return (new MediaResource($newMedia))->response()->setStatusCode(201);
    }

    /**
     * Update a media item.
     */
    public function update(Request $request, $id)
    {
        $media = Media::findOrFail($id);
        $this->authorize('update', $media);
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

        return new MediaResource($media);
    }

    /**
     * Delete a media item.
     */
    public function destroy($id)
    {
        $media = Media::findOrFail($id);
        $this->authorize('delete', $media);
        
        $this->mediaService->deleteMedia($media);

        return response()->noContent();
    }

    /**
     * Bulk delete media items.
     */
    public function bulkDelete(Request $request)
    {
        $this->authorize('bulkDelete', Media::class);

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
     * Download a single media item.
     */
    public function download(Request $request, $id)
    {
        $media = Media::findOrFail($id);
        $this->authorize('download', $media);
        $path = $media->getPath();
        $mimeType = $media->mime_type ?? @mime_content_type($path) ?? 'application/octet-stream';

        if (!$path || !file_exists($path)) {
            abort(404, 'File not found');
        }

        $fileName = $media->file_name ?? ('media-' . $media->id);

        $response = response()->download($path, $fileName, [
            'Content-Type' => $mimeType,
        ]);

        // Manually set CORS on binary response (HandleCors may not mutate BinaryFileResponse)
        $origin = $request->headers->get('origin');
        $response->headers->set('Access-Control-Allow-Origin', $origin ?: '*');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Expose-Headers', 'Content-Disposition');
        $response->headers->set('Access-Control-Allow-Headers', 'authorization, content-type, accept, origin, x-requested-with, range');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $response->headers->set('Cross-Origin-Resource-Policy', 'cross-origin');
        $response->headers->set('Access-Control-Max-Age', '0');
        $response->headers->set('Vary', 'Origin');

        return $response;
    }

    /**
     * Generate a temporary signed download link.
     */
    public function downloadLink(Request $request, $id)
    {
        $media = Media::findOrFail($id);
        $expires = now()->addMinutes(5);
        $signedUrl = URL::temporarySignedRoute(
            'signed-media-download',
            $expires,
            ['media' => $media->id]
        );

        $origin = $request->headers->get('origin');
        $resp = response()->json([
            'url' => $signedUrl,
            'expires_at' => $expires->toIso8601String(),
        ]);

        $origin = $request->headers->get('origin');
        $resp->headers->set('Access-Control-Allow-Origin', $origin ?: '*');
        $resp->headers->set('Access-Control-Allow-Credentials', 'true');
        $resp->headers->set('Access-Control-Expose-Headers', 'Content-Disposition');
        $resp->headers->set('Access-Control-Allow-Headers', 'authorization, content-type, accept, origin, x-requested-with, range');
        $resp->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $resp->headers->set('Cross-Origin-Resource-Policy', 'cross-origin');
        $resp->headers->set('Access-Control-Max-Age', '0');
        $resp->headers->set('Vary', 'Origin');

        return $resp;
    }

    /**
     * Bulk download media items as a ZIP archive.
     * Accepts a comma-separated list of ids via query: ?ids=1,2,3
     */
    public function bulkDownload(Request $request)
    {
        $idsParam = $request->query('ids', '');
        $ids = array_filter(explode(',', $idsParam));

        if (empty($ids)) {
            return response()->json(['message' => 'No media IDs provided'], 422);
        }

        foreach ($ids as $id) {
            if (!ctype_digit($id)) {
                return response()->json(['message' => 'Invalid media id: ' . $id], 422);
            }
        }

        $mediaItems = Media::whereIn('id', $ids)->get();

        if ($mediaItems->isEmpty()) {
            return response()->json(['message' => 'No media found for provided ids'], 404);
        }

        $zip = new ZipArchive();
        $zipFileName = 'media-' . now()->format('Ymd-His') . '-' . Str::random(6) . '.zip';
        $zipPath = storage_path('app/tmp/' . $zipFileName);

        if (!is_dir(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
        }

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return response()->json(['message' => 'Unable to create zip file'], 500);
        }

        foreach ($mediaItems as $media) {
            $path = $media->getPath();
            $downloadName = $media->file_name ?? ('media-' . $media->id);
            $mimeType = $media->mime_type ?? @mime_content_type($path) ?? 'application/octet-stream';

            if ($path && file_exists($path)) {
                $zip->addFile($path, $downloadName);
            }
        }

        $zip->close();

        $response = response()
            ->download($zipPath, $zipFileName, [
                'Content-Type' => 'application/zip',
            ])
            ->deleteFileAfterSend(true);

        // Manually set CORS on binary response
        $origin = $request->headers->get('origin');
        $response->headers->set('Access-Control-Allow-Origin', $origin ?: '*');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Expose-Headers', 'Content-Disposition');
        $response->headers->set('Access-Control-Allow-Headers', 'authorization, content-type, accept, origin, x-requested-with, range');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $response->headers->set('Cross-Origin-Resource-Policy', 'cross-origin');
        $response->headers->set('Access-Control-Max-Age', '0');
        $response->headers->set('Vary', 'Origin');

        return $response;
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
        try {
            return $this->modelResolver->getModelType($type);
        } catch (ValidationException $e) {
            // Fallback for 'library' type
            if ($type === 'library') {
                return MediaLibrary::class;
            }
            throw $e;
        }
    }

    protected function resolveModel(string $type, int $id): Model
    {
        try {
            return $this->modelResolver->resolveModel($type, $id);
        } catch (ValidationException $e) {
            // Fallback for 'library' type
            if ($type === 'library') {
                return MediaLibrary::findOrFail($id);
            }
            throw $e;
        }
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
