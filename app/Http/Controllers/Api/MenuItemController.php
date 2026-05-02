<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Support\MenuStaticRoutes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MenuItemController extends Controller
{
    public function store(Request $request, Menu $menu): JsonResponse
    {
        $this->authorize('update', $menu);

        $data = $this->validatedPayload($request, $menu, null);
        $data['menu_id'] = $menu->id;

        $item = MenuItem::query()->create($data);

        return response()->json([
            'success' => true,
            'data' => $item,
        ], 201);
    }

    public function update(Request $request, MenuItem $menuItem): JsonResponse
    {
        $this->authorize('update', $menuItem);
        $menu = $menuItem->menu;

        $data = $this->validatedPayload($request, $menu, $menuItem);
        $menuItem->update($data);

        return response()->json([
            'success' => true,
            'data' => $menuItem->fresh(),
        ]);
    }

    public function destroy(MenuItem $menuItem): JsonResponse
    {
        $this->authorize('delete', $menuItem);
        $menuItem->delete();

        return response()->noContent();
    }

    public function reorder(Request $request, Menu $menu): JsonResponse
    {
        $this->authorize('update', $menu);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', Rule::exists('menu_items', 'id')->where('menu_id', $menu->id)],
            'items.*.parent_id' => ['nullable', 'integer', Rule::exists('menu_items', 'id')->where('menu_id', $menu->id)],
            'items.*.sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ]);

        foreach ($validated['items'] as $row) {
            $id = (int) $row['id'];
            $parentId = array_key_exists('parent_id', $row) && $row['parent_id'] !== null
                ? (int) $row['parent_id']
                : null;

            if ($parentId === $id) {
                return response()->json([
                    'success' => false,
                    'message' => 'A menu item cannot be its own parent.',
                ], 422);
            }

            MenuItem::query()->where('menu_id', $menu->id)->whereKey($id)->update([
                'parent_id' => $parentId,
                'sort_order' => (int) $row['sort_order'],
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, Menu $menu, ?MenuItem $existing): array
    {
        $parentRule = [
            'sometimes',
            'nullable',
            'integer',
            Rule::exists('menu_items', 'id')->where('menu_id', $menu->id),
        ];
        if ($existing !== null) {
            $parentRule[] = Rule::notIn([$existing->id]);
        }

        $itemTypeRule = $existing === null
            ? ['required', 'string', Rule::in(MenuItem::ITEM_TYPES)]
            : ['sometimes', 'string', Rule::in(MenuItem::ITEM_TYPES)];

        $base = $request->validate([
            'parent_id' => $parentRule,
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'label' => ['nullable', 'string', 'max:255'],
            'item_type' => $itemTypeRule,
            'url' => ['nullable', 'string', 'max:2048'],
            'static_route_key' => ['nullable', 'string', 'max:32'],
            'reference_id' => ['nullable', 'integer', 'min:1'],
            'open_in_new_tab' => ['sometimes', 'boolean'],
        ]);

        $type = isset($base['item_type']) ? (string) $base['item_type'] : (string) ($existing?->item_type ?? '');
        if ($type === '') {
            abort(422, 'item_type is required.');
        }

        if ($type === MenuItem::TYPE_CUSTOM_LINK) {
            $urlRule = $existing === null ? ['required'] : ['sometimes', 'required'];
            $request->validate(['url' => array_merge($urlRule, ['string', 'max:2048'])]);
        }

        if ($type === MenuItem::TYPE_STATIC_ROUTE) {
            $keyRule = $existing === null ? ['required'] : ['sometimes', 'required'];
            $request->validate(['static_route_key' => array_merge($keyRule, ['string', Rule::in(MenuStaticRoutes::KEYS)])]);
        }

        if (in_array($type, [MenuItem::TYPE_PROJECT, MenuItem::TYPE_BLOG_POST, MenuItem::TYPE_SERVICE], true)) {
            $refRule = $existing === null ? ['required'] : ['sometimes', 'required'];
            $table = match ($type) {
                MenuItem::TYPE_PROJECT => 'projects',
                MenuItem::TYPE_BLOG_POST => 'blog_posts',
                MenuItem::TYPE_SERVICE => 'services',
                default => '',
            };
            $request->validate([
                'reference_id' => array_merge($refRule, ['integer', Rule::exists($table, 'id')]),
            ]);
        }

        $parentId = array_key_exists('parent_id', $base)
            ? $base['parent_id']
            : ($existing?->parent_id);
        $sortOrder = array_key_exists('sort_order', $base)
            ? (int) $base['sort_order']
            : ($existing !== null ? (int) $existing->sort_order : null);
        $label = array_key_exists('label', $base) ? $base['label'] : $existing?->label;
        $url = array_key_exists('url', $base) ? $base['url'] : $existing?->url;
        $staticKey = array_key_exists('static_route_key', $base) ? $base['static_route_key'] : $existing?->static_route_key;
        $referenceId = array_key_exists('reference_id', $base) ? $base['reference_id'] : $existing?->reference_id;
        $openInNew = array_key_exists('open_in_new_tab', $base)
            ? (bool) $base['open_in_new_tab']
            : ($existing !== null ? (bool) $existing->open_in_new_tab : false);

        $out = [
            'parent_id' => $parentId,
            'sort_order' => $sortOrder ?? 0,
            'label' => $label,
            'item_type' => $type,
            'url' => $type === MenuItem::TYPE_CUSTOM_LINK ? $url : null,
            'static_route_key' => $type === MenuItem::TYPE_STATIC_ROUTE ? $staticKey : null,
            'reference_id' => in_array($type, [MenuItem::TYPE_PROJECT, MenuItem::TYPE_BLOG_POST, MenuItem::TYPE_SERVICE], true)
                ? (int) $referenceId
                : null,
            'open_in_new_tab' => $openInNew,
        ];

        if ($existing === null && $sortOrder === null) {
            $max = (int) MenuItem::query()->where('menu_id', $menu->id)->max('sort_order');
            $out['sort_order'] = $max + 1;
        }

        return $out;
    }
}
