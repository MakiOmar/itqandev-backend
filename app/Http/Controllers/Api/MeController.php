<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CurrentUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $resolved = (new CurrentUserResource($user))->resolve();
        // #region agent log
        $logPath = dirname(__DIR__, 5) . DIRECTORY_SEPARATOR . 'debug-71ed8d.log';
        $perms = $resolved['permissions'] ?? [];
        file_put_contents(
            $logPath,
            json_encode([
                'sessionId' => '71ed8d',
                'hypothesisId' => 'A',
                'location' => 'MeController.php:show',
                'message' => 'me resolved user',
                'data' => [
                    'permissionsCount' => is_array($perms) ? count($perms) : -1,
                    'role' => $resolved['role'] ?? null,
                    'hasManageBlog' => is_array($perms) && in_array('manage blog', $perms, true),
                ],
                'timestamp' => (int) (microtime(true) * 1000),
            ], JSON_UNESCAPED_UNICODE) . "\n",
            FILE_APPEND
        );
        // #endregion

        return response()->json([
            'user' => $resolved,
        ]);
    }
}
