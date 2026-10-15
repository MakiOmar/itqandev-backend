<?php

namespace App\Http\Controllers\Api;

use App\Addons\AddonRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Addon dashboard links the signed-in user may open. Admins get the same list via GET /api/settings `addons`;
 * editors cannot read settings, so the sidebar falls back to this.
 */
class AddonNavController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $user->hasAnyRole(['super_admin', 'admin']);
        $items = array_values(array_filter(
            AddonRegistry::adminNav(),
            static fn (array $item) => $isAdmin || $item['permission'] === null || $user->can($item['permission']),
        ));

        return response()->json(['success' => true, 'data' => $items]);
    }
}
