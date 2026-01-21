<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Get project settings
     * Returns branding, general settings, and feature flags
     * 
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        // TODO: Load from database or config file
        // For now, return default settings that can be extended
        $settings = [
            // Branding
            'name' => config('app.name', 'Dashboard'),
            'logo' => null,
            'favicon' => null,
            'primaryColor' => null,
            'secondaryColor' => null,
            
            // General settings
            'description' => config('app.description', ''),
            'supportEmail' => config('mail.from.address', ''),
            'supportPhone' => null,
            
            // Feature flags (project-specific)
            'features' => [
                // Add feature flags here as needed
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Update project settings
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function update(Request $request): JsonResponse
    {
        // TODO: Validate and save settings to database or config file
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'logo' => 'sometimes|nullable|string',
            'favicon' => 'sometimes|nullable|string',
            'primaryColor' => 'sometimes|nullable|string|max:7',
            'secondaryColor' => 'sometimes|nullable|string|max:7',
            'description' => 'sometimes|nullable|string',
            'supportEmail' => 'sometimes|nullable|email|max:255',
            'supportPhone' => 'sometimes|nullable|string|max:50',
            'features' => 'sometimes|array',
        ]);

        // TODO: Save to database or config file
        // For now, just return success
        
        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully',
            'data' => $validated,
        ]);
    }
}
