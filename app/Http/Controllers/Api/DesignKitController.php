<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDesignKitRequest;
use App\Support\DesignKitResolver;
use App\Support\MarketingSettingsCache;
use App\Support\ProjectSettingsStore;
use Illuminate\Http\JsonResponse;

class DesignKitController extends Controller
{
    public function show(): JsonResponse
    {
        $this->authorize('manageSettings');
        $settings = ProjectSettingsStore::load();
        $kit = DesignKitResolver::normalize($settings[DesignKitResolver::SETTINGS_KEY] ?? null, $settings);

        return response()->json([
            'success' => true,
            'data' => $kit,
        ]);
    }

    public function update(UpdateDesignKitRequest $request): JsonResponse
    {
        $settings = ProjectSettingsStore::load();
        $kit = DesignKitResolver::normalize($request->validated(), $settings);
        ProjectSettingsStore::merge([DesignKitResolver::SETTINGS_KEY => $kit]);
        MarketingSettingsCache::forgetAll();

        return response()->json([
            'success' => true,
            'data' => $kit,
            'message' => 'Design kit saved.',
        ]);
    }
}
