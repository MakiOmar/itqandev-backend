<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\System\DatabaseBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class DatabaseBackupController extends Controller
{
    public function __construct(private readonly DatabaseBackupService $backups)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('manageSystemCache');
        unset($request);

        return response()->json([
            'data' => $this->backups->list(),
            'meta' => [
                'confirm_phrase' => $this->backups->confirmPhrase(),
                'driver' => config('database.default'),
                'max_files' => (int) config('database-backup.max_files', 20),
                'schedule' => $this->backups->scheduleMeta(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('manageSystemCache');
        unset($request);

        try {
            $created = $this->backups->create();
        } catch (Throwable $e) {
            return response()->json([
                'message' => $this->safeMessage($e, 'Failed to create database backup.'),
            ], 500);
        }

        return response()->json([
            'message' => 'Backup created.',
            'data' => [
                'filename' => $created['filename'],
                'size' => $created['size'],
                'created_at' => $created['created_at'],
            ],
        ], 201);
    }

    public function download(Request $request, string $filename): BinaryFileResponse|JsonResponse
    {
        $this->authorize('manageSystemCache');
        unset($request);

        try {
            $path = $this->backups->absolutePath($filename);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->download($path, $filename, [
            'Content-Type' => 'application/sql',
        ]);
    }

    public function destroy(Request $request, string $filename): JsonResponse
    {
        $this->authorize('manageSystemCache');
        unset($request);

        try {
            $this->backups->delete($filename);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json(['message' => 'Backup deleted.']);
    }

    public function restore(Request $request): JsonResponse
    {
        $this->authorize('manageSystemCache');

        $maxKb = (int) config('database-backup.max_upload_kb', 512000);
        $validated = $request->validate([
            'confirmation' => ['required', 'string'],
            'filename' => ['nullable', 'string', 'max:200'],
            'file' => ['nullable', 'file', 'max:'.$maxKb],
        ]);

        $expected = $this->backups->confirmPhrase();
        if (! hash_equals($expected, (string) $validated['confirmation'])) {
            return response()->json([
                'message' => 'Confirmation phrase does not match.',
                'errors' => [
                    'confirmation' => ['You must type '.$expected.' exactly to restore.'],
                ],
            ], 422);
        }

        $filename = isset($validated['filename']) ? trim((string) $validated['filename']) : '';
        $upload = $request->file('file');

        if ($filename === '' && $upload === null) {
            return response()->json([
                'message' => 'Provide a stored backup filename or upload a .sql file.',
            ], 422);
        }

        if ($filename !== '' && $upload !== null) {
            return response()->json([
                'message' => 'Provide either a stored backup filename or an uploaded file, not both.',
            ], 422);
        }

        try {
            if ($upload !== null) {
                $ext = strtolower((string) $upload->getClientOriginalExtension());
                if ($ext !== 'sql') {
                    return response()->json(['message' => 'Only .sql files can be restored.'], 422);
                }

                $tmpName = 'upload_'.now()->format('Ymd_His').'_'.bin2hex(random_bytes(4)).'.sql';
                $tmpPath = $this->backups->directory().DIRECTORY_SEPARATOR.$tmpName;
                File::put($tmpPath, File::get($upload->getRealPath()));

                try {
                    $this->backups->restoreFromPath($tmpPath);
                } finally {
                    if (File::isFile($tmpPath)) {
                        File::delete($tmpPath);
                    }
                }
            } else {
                $this->backups->restoreFromStoredFile($filename);
            }
        } catch (Throwable $e) {
            return response()->json([
                'message' => $this->safeMessage($e, 'Database restore failed.'),
            ], 500);
        }

        return response()->json([
            'message' => 'Database restored successfully.',
        ]);
    }

    private function safeMessage(Throwable $e, string $fallback): string
    {
        if (app()->hasDebugModeEnabled()) {
            return $e->getMessage() !== '' ? $e->getMessage() : $fallback;
        }

        return $fallback;
    }
}
