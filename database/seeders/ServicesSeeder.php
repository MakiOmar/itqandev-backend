<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Support\SiteLanguages;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class ServicesSeeder extends Seeder
{
    public function run(): void
    {
        if (Service::query()->exists()) {
            return;
        }

        $path = dirname(base_path()) . DIRECTORY_SEPARATOR . 'website' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'site.json';
        if (! File::exists($path)) {
            $path = base_path('..' . DIRECTORY_SEPARATOR . 'website' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'site.json');
        }
        if (! File::exists($path)) {
            return;
        }

        $decoded = json_decode(File::get($path), true);
        $rows = is_array($decoded) && isset($decoded['services']) && is_array($decoded['services']) ? $decoded['services'] : [];
        $defaultLocale = SiteLanguages::defaultCode();

        $order = 0;
        foreach ($rows as $row) {
            if (! is_array($row) || empty($row['slug'])) {
                continue;
            }
            Service::query()->create([
                'slug' => (string) $row['slug'],
                'content_locale' => $defaultLocale,
                'icon' => isset($row['icon']) ? (string) $row['icon'] : null,
                'sort_order' => $order++,
                'is_published' => true,
                'name' => (string) ($row['name'] ?? $row['slug']),
                'short_description' => (string) ($row['shortDescription'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'process' => is_array($row['process'] ?? null) ? array_values(array_map('strval', $row['process'])) : null,
                'deliverables' => is_array($row['deliverables'] ?? null) ? array_values(array_map('strval', $row['deliverables'])) : null,
            ]);
        }
    }
}
