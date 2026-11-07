<?php

namespace Tests\Fixtures\Addon;

use App\Services\ContentTypes\BaseContentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SampleItemContentType extends BaseContentType
{
    private const VERSION_KEY = 'sample-items:version';

    public function key(): string
    {
        return 'sample_item';
    }

    public function label(): string
    {
        return 'Sample item';
    }

    public function pluralLabel(): string
    {
        return 'Sample items';
    }

    public function modelClass(): string
    {
        return SampleItem::class;
    }

    public function module(): ?string
    {
        return SampleAddonServiceProvider::MODULE;
    }

    public function publicPathPrefixes(): array
    {
        return ['sample-items'];
    }

    public function seo(Model $record, string $locale): array
    {
        return [
            'title' => '<b>'.$record->title.'</b> deal',
            'image' => 'javascript:alert(1)',
            'json_ld' => ['@type' => 'Thing', 'name' => $record->title.'</script>'],
        ];
    }

    public function publicCacheVersion(): ?string
    {
        return (string) Cache::get(self::VERSION_KEY, 1);
    }

    public static function bump(): void
    {
        Cache::forever(self::VERSION_KEY, (int) Cache::get(self::VERSION_KEY, 1) + 1);
    }

    protected function applyTranslation(Model $record, string $locale): void {}
}
