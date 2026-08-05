<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Database\Seeder;

class PrimaryMenuSeeder extends Seeder
{
    /**
     * Default slug consumed by the marketing site header (`GET /api/public/menus/primary`).
     * When empty, seeds CMS page links (after page seeders) plus home custom link.
     */
    public function run(): void
    {
        $menu = Menu::query()->firstOrCreate(
            ['slug' => 'primary'],
            ['name' => 'Primary header']
        );

        if ($menu->items()->exists()) {
            return;
        }

        $sort = 0;
        $this->addCustom($menu, $sort++, 'Home', '/', 'الرئيسية');

        foreach (
            [
                'services' => ['Services', 'الخدمات'],
                'portfolio' => ['Portfolio', 'المحفظة'],
                'about' => ['About', 'من نحن'],
                'pricing' => ['Pricing', 'الأسعار'],
                'articles' => ['Blog', 'المدونة'],
                'contact' => ['Contact', 'تواصل'],
            ] as $slug => [$en, $ar]
        ) {
            $page = Page::query()->where('slug', $slug)->where('status', Page::STATUS_PUBLISHED)->first();
            if ($page === null) {
                continue;
            }
            $this->addPage($menu, $sort++, $page, $en, $ar);
        }
    }

    private function addCustom(Menu $menu, int $sort, string $label, string $url, string $labelAr): void
    {
        $item = MenuItem::query()->create([
            'menu_id' => $menu->id,
            'parent_id' => null,
            'sort_order' => $sort,
            'label' => $label,
            'item_type' => MenuItem::TYPE_CUSTOM_LINK,
            'url' => $url,
            'static_route_key' => null,
            'reference_id' => null,
            'open_in_new_tab' => false,
        ]);
        $item->translations()->updateOrCreate(
            ['locale' => 'ar'],
            ['label' => $labelAr]
        );
    }

    private function addPage(Menu $menu, int $sort, Page $page, string $label, string $labelAr): void
    {
        $item = MenuItem::query()->create([
            'menu_id' => $menu->id,
            'parent_id' => null,
            'sort_order' => $sort,
            'label' => $label,
            'item_type' => MenuItem::TYPE_PAGE,
            'url' => null,
            'static_route_key' => null,
            'reference_id' => $page->id,
            'open_in_new_tab' => false,
        ]);
        $item->translations()->updateOrCreate(
            ['locale' => 'ar'],
            ['label' => $labelAr]
        );
    }
}
