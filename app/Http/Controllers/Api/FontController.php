<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Font;
use App\Services\PublicMarketingShellService;
use App\Support\TypographyResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class FontController extends Controller
{
    private const SETTINGS_CACHE_KEY = 'project-settings';

    private const SETTINGS_FILE_PATH = 'project-settings.json';

    /**
     * @return array<string, mixed>
     */
    private function loadStoredSettings(): array
    {
        if (! Storage::disk('local')->exists(self::SETTINGS_FILE_PATH)) {
            return [];
        }

        $content = Storage::disk('local')->get(self::SETTINGS_FILE_PATH);
        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function flushPublicCaches(): void
    {
        Cache::forget(self::SETTINGS_CACHE_KEY);
        PublicMarketingShellService::forgetShellCaches();
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
            Rule::when(
                fn () => true,
                ['regex:/^(https?:\/\/|\/)/i']
            ),
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
                'mimes:woff,woff2,ttf,eot,svg',
            ],
            'format' => ['required', 'string', Rule::in(['woff2', 'woff', 'ttf', 'eot', 'svg'])],
        ]);

        $file = $request->file('file');
        $format = $data['format'];
        $ext = $file->getClientOriginalExtension() ?: $format;
        $filename = uniqid('font_', true).'.'.$ext;

        $path = $file->storeAs('fonts', $filename, 'public');
        $url = Storage::disk('public')->url($path);

        return response()->json([
            'success' => true,
            'data' => [
                'url' => $url,
                'path' => $path,
                'format' => $format,
            ],
        ]);
    }
}
