<?php

use App\Models\MenuItem;
use App\Models\Page;
use App\Support\CmsPublicPaths;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Convert legacy static_route menu items to CMS page refs or home custom links.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menu_items')) {
            return;
        }

        $map = CmsPublicPaths::legacyStaticKeyToPageSlug();
        $pagesBySlug = Page::query()
            ->whereIn('slug', array_values(array_filter($map)))
            ->get()
            ->keyBy(fn (Page $p) => strtolower((string) $p->slug));

        MenuItem::query()
            ->where('item_type', MenuItem::TYPE_STATIC_ROUTE)
            ->orderBy('id')
            ->each(function (MenuItem $item) use ($map, $pagesBySlug): void {
                $key = strtolower(trim((string) ($item->static_route_key ?? '')));
                if ($key === '' || ! array_key_exists($key, $map)) {
                    $item->delete();

                    return;
                }

                $pageSlug = $map[$key];
                if ($pageSlug === null) {
                    $item->item_type = MenuItem::TYPE_CUSTOM_LINK;
                    $item->url = '/';
                    $item->static_route_key = null;
                    $item->reference_id = null;
                    $item->save();

                    return;
                }

                $page = $pagesBySlug->get($pageSlug);
                if ($page === null) {
                    // Fall back to custom link at the pretty path until the CMS page is seeded.
                    $item->item_type = MenuItem::TYPE_CUSTOM_LINK;
                    $item->url = CmsPublicPaths::pathForPageSlug($pageSlug);
                    $item->static_route_key = null;
                    $item->reference_id = null;
                    $item->save();

                    return;
                }

                $item->item_type = MenuItem::TYPE_PAGE;
                $item->reference_id = $page->id;
                $item->static_route_key = null;
                $item->url = null;
                $item->save();
            });
    }

    public function down(): void
    {
        // Irreversible data migration.
    }
};
