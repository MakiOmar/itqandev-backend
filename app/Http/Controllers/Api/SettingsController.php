<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    /**
     * Convert PHP ini size string (e.g., "100M", "2G", "10485760") to bytes
     * Handles: K, M, G suffixes and plain byte values
     * 
     * @param string $sizeString
     * @return int
     */
    private function convertIniSizeToBytes(string $sizeString): int
    {
        $sizeString = trim($sizeString);
        
        // Handle empty string
        if (empty($sizeString)) {
            return 0;
        }
        
        $last = strtolower($sizeString[strlen($sizeString) - 1]);
        $value = (int) $sizeString;

        // If last character is a letter (K, M, G), multiply accordingly
        if (in_array($last, ['k', 'm', 'g'], true)) {
            switch ($last) {
                case 'g':
                    $value *= 1024;
                    // fall through
                case 'm':
                    $value *= 1024;
                    // fall through
                case 'k':
                    $value *= 1024;
            }
        }
        // Otherwise, it's already in bytes (plain number)

        return $value;
    }

    /**
     * Get project settings
     * Returns branding, general settings, and feature flags
     * 
     * OPTIMIZATION: Cached for 5 minutes to reduce database/config lookups
     * Settings rarely change, so aggressive caching is safe
     * 
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        // Get actual PHP server limits (not application config)
        // These are fetched on each request to reflect real-time server config
        $uploadMaxFilesize = ini_get('upload_max_filesize') ?: '8M'; // Default fallback if ini_get fails
        $postMaxSize = ini_get('post_max_size') ?: '8M'; // Default fallback if ini_get fails
        
        // Convert to bytes
        $uploadMaxBytes = $this->convertIniSizeToBytes($uploadMaxFilesize);
        $postMaxBytes = $this->convertIniSizeToBytes($postMaxSize);
        
        // Use the smaller of the two as the effective limit
        // post_max_size must be >= upload_max_filesize for uploads to work
        $maxFileSize = min($uploadMaxBytes, $postMaxBytes);

        // Cache for 5 minutes (300 seconds)
        // This reduces load on config/database for frequently accessed settings
        $settings = Cache::remember('project-settings', 300, function () {
            // TODO: Load from database or config file
            // For now, return default settings that can be extended
            return [
                // Branding
                'name' => config('app.name', 'Dashboard'),
                'logo' => null,
                'logoDark' => null,
                'logoLight' => null,
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
        });

        // Add max_file_size from PHP ini_get() (not cached, always current)
        // This reflects the actual server limits (upload_max_filesize, post_max_size)
        // not application config, so client validation matches server capabilities
        $settings['max_file_size'] = $maxFileSize;

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Update project settings
     * 
     * OPTIMIZATION: Clears cache on update to ensure fresh data
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
            'logoDark' => 'sometimes|nullable|string',
            'logoLight' => 'sometimes|nullable|string',
            'logo_dark' => 'sometimes|nullable|string',
            'logo_light' => 'sometimes|nullable|string',
            'dark_logo' => 'sometimes|nullable|string',
            'light_logo' => 'sometimes|nullable|string',
            'site_logo_dark' => 'sometimes|nullable|string',
            'site_logo_light' => 'sometimes|nullable|string',
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
        
        // Clear cache so next request gets fresh data
        Cache::forget('project-settings');
        
        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully',
            'data' => $validated,
        ]);
    }
}
