<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\PageDocument;
use App\Support\MenuBadgePalette;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MenuApiController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json($this->documents());
    }

    public function update(Request $request, string $key): JsonResponse
    {
        abort_unless(in_array($key, ['header', 'footer'], true), 404);
        $items = $key === 'header' ? $this->validateHeader($request) : $this->validateFooter($request);
        $items = $this->resolveInternalTargets($items, $key);

        DB::transaction(function () use ($key, $items): void {
            $menu = Menu::query()->updateOrCreate(['key' => $key], ['name' => ucfirst($key).' navigation']);
            $menu->items()->delete();
            foreach ($items as $item) {
                $root = $menu->items()->create($this->attributes($key, $item));
                $children = $key === 'header' ? ($item['columns'] ?? []) : ($item['links'] ?? []);
                foreach ($children as $child) {
                    $second = $menu->items()->create($this->attributes($key, $child, $root));
                    if ($key === 'header') {
                        foreach ($child['items'] ?? [] as $leaf) {
                            $menu->items()->create($this->attributes($key, $leaf, $second));
                        }
                    }
                }
            }
        });

        $documents = $this->documents();

        return response()->json(['data' => $key === 'header' ? $documents['headerMenus'] : $documents['footerMenus']]);
    }

    private function documents(): array
    {
        $menus = Menu::query()->whereIn('key', ['header', 'footer'])->with('rootItems.children.children')->get()->keyBy('key');

        return [
            'headerMenus' => ($menus['header']?->rootItems ?? collect())->map(fn (MenuItem $item) => $this->headerItem($item))->values(),
            'footerMenus' => ($menus['footer']?->rootItems ?? collect())->map(fn (MenuItem $item) => $this->footerItem($item))->values(),
        ];
    }

    private function validateHeader(Request $request): array
    {
        return $request->validate([
            'items' => ['required', 'array', 'max:30'],
            'items.*.title' => ['required', 'string', 'max:255'], 'items.*.url' => ['required', 'string', 'max:2048'],
            'items.*.linkType' => ['required', Rule::in(['category', 'collection', 'product', 'page', 'custom_url'])],
            'items.*.targetId' => ['nullable', 'string', 'max:255'], 'items.*.icon' => ['nullable', 'string', 'max:100'],
            'items.*.badge' => ['nullable', 'string', 'max:80'], 'items.*.badgeColor' => ['nullable', Rule::in(MenuBadgePalette::values())], 'items.*.isHighlighted' => ['sometimes', 'boolean'],
            'items.*.displayStyle' => ['required', Rule::in(['dropdown', 'megamenu', 'simple_link'])],
            'items.*.isActive' => ['required', 'boolean'], 'items.*.sortOrder' => ['required', 'integer', 'min:0', 'max:65535'],
            'items.*.columns' => ['array', 'max:20'], 'items.*.columns.*.title' => ['required', 'string', 'max:255'],
            'items.*.columns.*.url' => ['required', 'string', 'max:2048'], 'items.*.columns.*.linkType' => ['required', Rule::in(['category', 'collection', 'product', 'page', 'custom_url'])],
            'items.*.columns.*.targetId' => ['nullable', 'string', 'max:255'], 'items.*.columns.*.icon' => ['nullable', 'string', 'max:100'],
            'items.*.columns.*.description' => ['nullable', 'string', 'max:1000'], 'items.*.columns.*.badge' => ['nullable', 'string', 'max:80'], 'items.*.columns.*.badgeColor' => ['nullable', Rule::in(MenuBadgePalette::values())],
            'items.*.columns.*.isActive' => ['required', 'boolean'], 'items.*.columns.*.sortOrder' => ['required', 'integer', 'min:0', 'max:65535'],
            'items.*.columns.*.items' => ['array', 'max:100'], 'items.*.columns.*.items.*.title' => ['required', 'string', 'max:255'],
            'items.*.columns.*.items.*.url' => ['required', 'string', 'max:2048'], 'items.*.columns.*.items.*.linkType' => ['required', Rule::in(['category', 'collection', 'product', 'page', 'custom_url'])],
            'items.*.columns.*.items.*.targetId' => ['nullable', 'string', 'max:255'], 'items.*.columns.*.items.*.badge' => ['nullable', 'string', 'max:80'], 'items.*.columns.*.items.*.badgeColor' => ['nullable', Rule::in(MenuBadgePalette::values())],
            'items.*.columns.*.items.*.isExternal' => ['nullable', 'boolean'], 'items.*.columns.*.items.*.isActive' => ['required', 'boolean'],
            'items.*.columns.*.items.*.sortOrder' => ['required', 'integer', 'min:0', 'max:65535'],
        ])['items'];
    }

    private function validateFooter(Request $request): array
    {
        return $request->validate([
            'items' => ['required', 'array', 'max:20'], 'items.*.sectionTitle' => ['required', 'string', 'max:255'],
            'items.*.isActive' => ['required', 'boolean'], 'items.*.sortOrder' => ['required', 'integer', 'min:0', 'max:65535'],
            'items.*.links' => ['array', 'max:100'], 'items.*.links.*.title' => ['required', 'string', 'max:255'],
            'items.*.links.*.url' => ['required', 'string', 'max:2048'], 'items.*.links.*.linkType' => ['required', Rule::in(['category', 'collection', 'product', 'page', 'custom_url'])],
            'items.*.links.*.targetId' => ['nullable', 'string', 'max:255'], 'items.*.links.*.badge' => ['nullable', 'string', 'max:80'], 'items.*.links.*.badgeColor' => ['nullable', Rule::in(MenuBadgePalette::values())],
            'items.*.links.*.isExternal' => ['nullable', 'boolean'], 'items.*.links.*.isActive' => ['required', 'boolean'],
            'items.*.links.*.sortOrder' => ['required', 'integer', 'min:0', 'max:65535'],
        ])['items'];
    }

    /**
     * Internal navigation links are stored as both a stable entity ID and a
     * derived public URL. Older links without a target ID remain untouched so
     * an unrelated edit does not break a saved legacy menu.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function resolveInternalTargets(array $items, string $key): array
    {
        foreach ($items as $index => $item) {
            $items[$index] = $this->resolveItemTarget($item, "items.$index");
            $childrenKey = $key === 'header' ? 'columns' : 'links';

            foreach ($items[$index][$childrenKey] ?? [] as $childIndex => $child) {
                $childPath = "items.$index.$childrenKey.$childIndex";
                $items[$index][$childrenKey][$childIndex] = $this->resolveItemTarget($child, $childPath);

                if ($key !== 'header') {
                    continue;
                }

                foreach ($items[$index][$childrenKey][$childIndex]['items'] ?? [] as $leafIndex => $leaf) {
                    $leafPath = "$childPath.items.$leafIndex";
                    $items[$index][$childrenKey][$childIndex]['items'][$leafIndex] = $this->resolveItemTarget($leaf, $leafPath);
                }
            }
        }

        return $items;
    }

    /** @param array<string, mixed> $item */
    private function resolveItemTarget(array $item, string $path): array
    {
        $linkType = $item['linkType'] ?? 'custom_url';
        $targetId = trim((string) ($item['targetId'] ?? ''));

        if (! in_array($linkType, ['page', 'category', 'collection'], true) || $targetId === '') {
            return $item;
        }

        $url = match ($linkType) {
            'page' => PageDocument::query()
                ->where('page_id', $targetId)
                ->where('stage', PageDocument::STAGE_PUBLISHED)
                ->whereHas('page', fn ($query) => $query->where('status', 'published'))
                ->value('slug'),
            'category' => Category::query()->whereKey($targetId)->where('is_active', true)->value('slug'),
            'collection' => Collection::query()->whereKey($targetId)->where('is_active', true)->where('status', 'active')->value('slug'),
        };

        if (! $url) {
            throw ValidationException::withMessages(["$path.targetId" => ['The selected '.$linkType.' is unavailable for storefront navigation.']]);
        }

        $item['targetId'] = $targetId;
        $item['url'] = match ($linkType) {
            'page' => '/pages/'.$url,
            'category' => '/categories/'.$url,
            'collection' => '/collections/'.$url,
        };

        return $item;
    }

    private function attributes(string $key, array $item, ?MenuItem $parent = null): array
    {
        $isFooterRoot = $key === 'footer' && $parent === null;

        return [
            'parent_id' => $parent?->id, 'label' => $isFooterRoot ? $item['sectionTitle'] : $item['title'],
            'url' => $isFooterRoot ? '#' : $item['url'], 'position' => $item['sortOrder'], 'link_type' => $item['linkType'] ?? 'custom_url',
            'target_id' => $item['targetId'] ?? null, 'icon' => $item['icon'] ?? null, 'description' => $item['description'] ?? null,
            'badge' => $item['badge'] ?? null, 'badge_color' => $item['badgeColor'] ?? null, 'is_external' => $item['isExternal'] ?? false, 'is_active' => $item['isActive'],
            'is_highlighted' => $item['isHighlighted'] ?? false, 'display_style' => $item['displayStyle'] ?? null,
            'featured_title' => $item['featuredTitle'] ?? null, 'featured_description' => $item['featuredDescription'] ?? null,
            'featured_price' => $item['featuredPrice'] ?? null, 'featured_image_url' => $item['featuredImageUrl'] ?? null,
            'featured_cta_label' => $item['featuredCtaLabel'] ?? null, 'featured_cta_url' => $item['featuredCtaUrl'] ?? null,
        ];
    }

    private function headerItem(MenuItem $item): array
    {
        return ['id' => (string) $item->id, 'title' => $item->label, 'url' => $item->url, 'linkType' => $item->link_type, 'targetId' => $item->target_id,
            'icon' => $item->icon, 'badge' => $item->badge, 'badgeColor' => $item->badge_color, 'isHighlighted' => $item->is_highlighted, 'displayStyle' => $item->display_style ?? ($item->children->isEmpty() ? 'simple_link' : 'megamenu'),
            'isActive' => $item->is_active, 'sortOrder' => $item->position, 'featuredTitle' => $item->featured_title, 'featuredDescription' => $item->featured_description,
            'featuredPrice' => $item->featured_price, 'featuredImageUrl' => $item->featured_image_url, 'featuredCtaLabel' => $item->featured_cta_label, 'featuredCtaUrl' => $item->featured_cta_url,
            'columns' => $item->children->map(fn (MenuItem $child) => ['id' => (string) $child->id, 'title' => $child->label, 'url' => $child->url, 'linkType' => $child->link_type, 'targetId' => $child->target_id, 'icon' => $child->icon, 'description' => $child->description, 'badge' => $child->badge, 'badgeColor' => $child->badge_color, 'isActive' => $child->is_active, 'sortOrder' => $child->position,
                'items' => $child->children->map(fn (MenuItem $leaf) => ['id' => (string) $leaf->id, 'title' => $leaf->label, 'url' => $leaf->url, 'linkType' => $leaf->link_type, 'targetId' => $leaf->target_id, 'badge' => $leaf->badge, 'badgeColor' => $leaf->badge_color, 'isExternal' => $leaf->is_external, 'isActive' => $leaf->is_active, 'sortOrder' => $leaf->position])->values()])->values()];
    }

    private function footerItem(MenuItem $item): array
    {
        return ['id' => (string) $item->id, 'sectionTitle' => $item->label, 'isActive' => $item->is_active, 'sortOrder' => $item->position,
            'links' => $item->children->map(fn (MenuItem $link) => ['id' => (string) $link->id, 'title' => $link->label, 'url' => $link->url, 'linkType' => $link->link_type, 'targetId' => $link->target_id, 'badge' => $link->badge, 'badgeColor' => $link->badge_color, 'isExternal' => $link->is_external, 'isActive' => $link->is_active, 'sortOrder' => $link->position])->values()];
    }
}
