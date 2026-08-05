<?php

require dirname(__DIR__) . '/vendor/autoload.php';
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$envelope = [
    'format' => 'credocode.builder-export',
    'version' => 1,
    'builder' => 'page',
    'exported_at' => gmdate('c'),
    'document' => [
        'sections' => App\Services\Appearance\ContactPageLayout::sections(),
    ],
];

$out = dirname(__DIR__, 2) . '/website/src/lib/admin/page-templates/contact-page.builder.json';
@mkdir(dirname($out), 0777, true);
file_put_contents($out, json_encode($envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo "Wrote {$out}\n";
