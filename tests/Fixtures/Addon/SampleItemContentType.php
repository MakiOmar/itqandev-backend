<?php

namespace Tests\Fixtures\Addon;

use App\Services\ContentTypes\BaseContentType;
use Illuminate\Database\Eloquent\Model;

class SampleItemContentType extends BaseContentType
{
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

    protected function applyTranslation(Model $record, string $locale): void {}
}
