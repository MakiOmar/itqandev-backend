<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Font;
use App\Rules\ValidatesStoragePath;
use App\Support\MarketingSettingsCache;
use App\Support\ProjectSettingsStore;
use App\Support\TypographyResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class FontController extends Controller
{
    /** @var list<string> */
    private const FONT_EXTENSIONS = ['woff', 'woff2', 'ttf', 'eot', 'svg'];

    /** @var list<string> */
    private const FONT_MIME_TYPES = [
        'font/woff',
        'font/woff2',
        'font/ttf',
        'font/sfnt',
        'application/font-woff',
        'application/font-woff2',
        'application/x-font-woff',
        'application/x-font-ttf',
        'application/font-sfnt',
        'application/vnd.ms-fontobject',
        'image/svg+xml',
        'application/svg+xml',
        'text/xml',
        'text/plain',
        'application/octet-stream',
        'binary/octet-stream',
    ];

    /**
     * @return array<string, mixed>
     */
    private function loadStoredSettings(): array
    {
        return ProjectSettingsStore::load();
    }

    private function flushPublicCaches(): void
    {
        MarketingSettingsCache::forgetAll();
    }

    private function publicStoragePath(string $path): string
    {
        $normalized = str_replace('\\', '/', ltrim($path, '/'));

        return '/storage/'.$normalized;
    }

    /**
     * Persist relative /storage/... paths; accept legacy absolute app URLs from uploads.
     */
    private function normalizeFontFilePath(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if (str_starts_with($trimmed, '/storage/')) {
            return $trimmed;
        }

        if (preg_match('#^https?://[^/]+(/storage/.+)$#i', $trimmed, $matches) === 1) {
            return $matches[1];
        }

        return $trimmed;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeFontFileFields(array $data): array
    {
        foreach (['file_woff2', 'file_woff', 'file_ttf', 'file_eot', 'file_svg'] as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = $this->normalizeFontFilePath($data[$key]);
            }
        }

        return $data;
    }

    private function validateFontUploadFile(UploadedFile $file, string $expectedFormat): bool
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: '');
        if (! in_array($ext, self::FONT_EXTENSIONS, true)) {
            return false;
        }

        if ($ext !== strtolower($expectedFormat)) {
            return false;
        }

        $mime = strtolower((string) $file->getMimeType());

        return in_array($mime, self::FONT_MIME_TYPES, true);
    }

    /**
     * @return array<int, string>
     */
    private function fontFileRules(bool $required = false): array
    {
        $prefix = $required ? 'required' : 'nullable';

        return [
            $prefix,
            'string',
            'max:2048',
            new ValidatesStoragePath(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertHasAtLeastOneSource(array $data): void
    {
        $keys = ['file_woff2', 'file_woff', 'file_ttf', 'file_eot', 'file_svg'];
        foreach ($keys as $key) {
            $val = $data[$key] ?? null;
            if (is_string($val) && trim($val) !== '') {
                return;
            }
        }

        abort(422, 'At least one font file URL is required (woff2, woff, ttf, eot, or svg).');
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Font::class);

        $search = trim((string) $request->query('search', ''));
        $perPage = max(1, min((int) $request->query('per_page', 50), 100));

        $query = Font::query()->orderBy('name');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('css_family', 'like', '%'.$search.'%');
            });
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Font::class);

        $request->merge($this->normalizeFontFileFields($request->all()));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'css_family' => ['required', 'string', 'max:120', 'regex:/^[a-zA-Z0-9 _-]+$/'],
            'file_woff2' => $this->fontFileRules(),
            'file_woff' => $this->fontFileRules(),
            'file_ttf' => $this->fontFileRules(),
            'file_eot' => $this->fontFileRules(),
            'file_svg' => $this->fontFileRules(),
        ]);

        $this->assertHasAtLeastOneSource($data);

        $font = Font::query()->create($data);
        $this->flushPublicCaches();

        return response()->json([
            'success' => true,
            'data' => $font,
        ], 201);
    }

    public function show(Font $font): JsonResponse
    {
        $this->authorize('view', $font);

        return response()->json([
            'success' => true,
            'data' => $font,
        ]);
    }

    public function update(Request $request, Font $font): JsonResponse
    {
        $this->authorize('update', $font);

        $request->merge($this->normalizeFontFileFields($request->all()));

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'css_family' => ['sometimes', 'string', 'max:120', 'regex:/^[a-zA-Z0-9 _-]+$/'],
            'file_woff2' => $this->fontFileRules(),
            'file_woff' => $this->fontFileRules(),
            'file_ttf' => $this->fontFileRules(),
            'file_eot' => $this->fontFileRules(),
            'file_svg' => $this->fontFileRules(),
        ]);

        $merged = array_merge($font->only([
            'name',
            'css_family',
            'file_woff2',
            'file_woff',
            'file_ttf',
            'file_eot',
            'file_svg',
        ]), $data);

        $this->assertHasAtLeastOneSource($merged);

        $font->update($data);
        $this->flushPublicCaches();

        return response()->json([
            'success' => true,
            'data' => $font->fresh(),
        ]);
    }

    public function destroy(Font $font): JsonResponse
    {
        $this->authorize('delete', $font);

        $settings = $this->loadStoredSettings();
        if (TypographyResolver::isFontReferencedInSettings($settings, (int) $font->id)) {
            return response()->json([
                'success' => false,
                'message' => 'This font is assigned in Typography settings and cannot be deleted.',
            ], 422);
        }

        $font->delete();
        $this->flushPublicCaches();

        return response()->json([
            'success' => true,
            'message' => 'Font deleted',
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $this->authorize('create', Font::class);

        $data = $request->validate([
            'file' => [
                'required',
                'file',
                'max:'.max(1, (int) config('media.max_file_size', 10485760) / 1024),
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if (! $value instanceof UploadedFile) {
                        $fail(__('Invalid file upload.'));

                        return;
                    }

                    $format = strtolower((string) $request->input('format', ''));
                    if (! in_array($format, self::FONT_EXTENSIONS, true)) {
                        $fail(__('The format field is invalid.'));

                        return;
                    }

                    if (! $this->validateFontUploadFile($value, $format)) {
                        $fail(__('The file field must be a file of type: woff, woff2, ttf, eot, svg.'));
                    }
                },
            ],
            'format' => ['required', 'string', Rule::in(self::FONT_EXTENSIONS)],
        ]);

        $file = $request->file('file');
        $format = $data['format'];
        $ext = strtolower($file->getClientOriginalExtension() ?: $format);
        $filename = uniqid('font_', true).'.'.$ext;

        $path = $file->storeAs('fonts', $filename, 'public');
        $publicPath = $this->publicStoragePath($path);

        return response()->json([
            'success' => true,
            'data' => [
                'url' => $publicPath,
                'path' => $path,
                'format' => $format,
            ],
        ]);
    }
}
