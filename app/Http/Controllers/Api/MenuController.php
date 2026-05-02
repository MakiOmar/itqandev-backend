<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class MenuController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Menu::class);

        $menus = Menu::query()->orderBy('name')->get(['id', 'name', 'slug', 'created_at', 'updated_at']);

        return response()->json([
            'success' => true,
            'data' => $menus,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Menu::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_-]+$/', Rule::unique('menus', 'slug')],
        ]);

        $menu = Menu::query()->create($data);

        return response()->json([
            'success' => true,
            'data' => $menu,
        ], 201);
    }

    public function show(Menu $menu): JsonResponse
    {
        $this->authorize('view', $menu);

        $items = $menu->items()->orderBy('sort_order')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $menu->id,
                'name' => $menu->name,
                'slug' => $menu->slug,
                'items' => $this->nestAdminItems($items),
            ],
        ]);
    }

    public function update(Request $request, Menu $menu): JsonResponse
    {
        $this->authorize('update', $menu);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'slug' => ['sometimes', 'string', 'max:64', 'regex:/^[a-z0-9_-]+$/', Rule::unique('menus', 'slug')->ignore($menu->id)],
        ]);

        $menu->update($data);

        return response()->json([
            'success' => true,
            'data' => $menu->fresh(),
        ]);
    }

    public function destroy(Menu $menu): JsonResponse
    {
        $this->authorize('delete', $menu);
        $menu->delete();

        return response()->noContent();
    }

    /**
     * @param  Collection<int, MenuItem>  $all
     * @return list<array<string, mixed>>
     */
    private function nestAdminItems(Collection $all, ?int $parentId = null): array
    {
        $branch = [];
        foreach ($all->where('parent_id', $parentId)->sortBy('sort_order') as $item) {
            $branch[] = [
                'id' => $item->id,
                'parent_id' => $item->parent_id,
                'sort_order' => $item->sort_order,
                'label' => $item->label,
                'item_type' => $item->item_type,
                'url' => $item->url,
                'static_route_key' => $item->static_route_key,
                'reference_id' => $item->reference_id,
                'open_in_new_tab' => $item->open_in_new_tab,
                'children' => $this->nestAdminItems($all, $item->id),
            ];
        }

        return $branch;
    }
}
