<?php

namespace Tests\Fixtures\Addon;

use App\Addons\AddonServiceProvider;

/**
 * Test-only addon proving the host contract. A real addon ships this class from its own Composer repo
 * and lists it in `extra.laravel.providers`; the tests register it by hand.
 */
class SampleAddonServiceProvider extends AddonServiceProvider
{
    public const MODULE = 'sample_addon';

    public function moduleKey(): string
    {
        return self::MODULE;
    }

    public function contentTypes(): array
    {
        return [SampleItemContentType::class];
    }

    public function widgets(): array
    {
        return [
            'sample_note' => [
                'label' => 'Sample note',
                'category' => 'Sample addon',
                'default_settings' => ['text' => 'Hello from the sample addon'],
                'settings_fields' => [
                    ['key' => 'text', 'type' => 'text', 'label' => 'Text', 'translatable' => true],
                ],
            ],
        ];
    }

    public function adminNav(): array
    {
        return [
            [
                'label' => ['en' => 'Sample items', 'ar' => 'عناصر تجريبية'],
                'href' => '/admin/addons/sample_addon/',
                'permission' => 'manage sample items',
            ],
        ];
    }

    protected function apiRoutesPath(): ?string
    {
        return __DIR__.'/routes.php';
    }
}
