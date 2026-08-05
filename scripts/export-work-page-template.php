<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Appearance\WorkPageLayout;

$payload = [
    'format' => 'credocode.builder-export',
    'version' => 1,
    'builder' => 'page',
    'exported_at' => now()->toIso8601String(),
    'document' => [
        'sections' => WorkPageLayout::sections(),
    ],
];

$out = dirname(__DIR__, 2) . '/website/src/lib/admin/page-templates/work-page.builder.json';
file_put_contents($out, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
echo "Wrote {$out}\n";
