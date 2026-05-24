<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class PublicSiteContentController extends Controller
{
    private const SETTINGS_FILE_PATH = 'project-settings.json';

    private const SETTINGS_CACHE_KEY = 'project-settings';

    public function show(): JsonResponse
    {
        $settings = Cache::remember(self::SETTINGS_CACHE_KEY, 300, function () {
            if (! Storage::disk('local')->exists(self::SETTINGS_FILE_PATH)) {
                return [];
            }
            $decoded = json_decode(Storage::disk('local')->get(self::SETTINGS_FILE_PATH), true);

            return is_array($decoded) ? $decoded : [];
        });

        $marketing = is_array($settings['marketing_site_content'] ?? null)
            ? $settings['marketing_site_content']
            : [];

        $contact = is_array($marketing['contact'] ?? null)
            ? $marketing['contact']
            : [
                'email' => $settings['site_email'] ?? $settings['supportEmail'] ?? null,
                'phone' => $settings['site_phone'] ?? $settings['supportPhone'] ?? null,
                'address' => $settings['site_address'] ?? null,
                'socials' => [],
            ];

        return response()->json([
            'success' => true,
            'data' => [
                'pricingTiers' => $marketing['pricingTiers'] ?? $marketing['pricing_tiers'] ?? [],
                'faq' => $marketing['faq'] ?? [],
                'contact' => $contact,
                'about' => $marketing['about'] ?? [],
                'techStack' => $marketing['techStack'] ?? $marketing['tech_stack'] ?? [],
            ],
        ]);
    }
}
