<?php

namespace App\Support;

use App\Models\Category;
use App\Models\MenuSection;
use App\Models\MenuTab;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MenuHierarchyCatalog
{
    /**
     * Admin ürün formu: kategori → sekmeler → gruplar (API yedeklemesi).
     *
     * @param  Collection<int, Category>  $categories
     * @return array<string, array{tabs: list<array<string, mixed>>}>
     */
    public static function forCategories(Collection $categories): array
    {
        if (! Schema::hasTable('menu_tabs')) {
            return [];
        }

        $categories->load([
            'menuTabs' => fn ($q) => $q
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->with([
                    'menuSections' => fn ($q) => $q
                        ->where('is_active', true)
                        ->orderBy('sort_order')
                        ->orderBy('id'),
                ]),
        ]);

        $catalog = [];

        foreach ($categories as $category) {
            $visibleTabIds = self::visibleMenuTabIds($category);

            $catalog[(string) $category->id] = [
                'tabs' => $category->menuTabs
                    ->map(fn (MenuTab $tab) => self::formatTab(
                        $tab,
                        $visibleTabIds->contains($tab->id)
                    ))
                    ->values()
                    ->all(),
            ];
        }

        return $catalog;
    }

    /**
     * @return array<string, mixed>
     */
    public static function formatTab(MenuTab $tab, ?bool $visibleOnMenu = null): array
    {
        $blocking = self::blockingProductsForTab($tab->id);

        return [
            'id' => $tab->id,
            'name' => $tab->localizedName('tr'),
            'slug' => $tab->slug,
            'layout' => Schema::hasColumn('menu_tabs', 'layout')
                ? ($tab->layout ?? MenuTab::LAYOUT_GROUPED)
                : MenuTab::LAYOUT_GROUPED,
            'sort_order' => (int) $tab->sort_order,
            'products_count' => $blocking->count(),
            'can_delete' => $blocking->isEmpty(),
            'visible_on_menu' => $visibleOnMenu ?? true,
            'blocking_products' => $blocking
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->localizedName('tr'),
                    'is_available' => (bool) $product->is_available,
                ])
                ->values()
                ->all(),
            'sections' => $tab->relationLoaded('menuSections')
                ? $tab->menuSections
                    ->map(fn (MenuSection $section) => self::formatSection($section))
                    ->values()
                    ->all()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function formatSection(MenuSection $section): array
    {
        return [
            'id' => $section->id,
            'name' => $section->localizedName('tr'),
            'slug' => $section->slug,
            'products_count' => self::countSectionProducts($section->id),
            'can_delete' => self::countSectionProducts($section->id) === 0,
        ];
    }

    /**
     * QR menüde görünen sekme kimlikleri (slug tekrarı birleştirilir).
     *
     * @return Collection<int, int>
     */
    public static function visibleMenuTabIds(Category $category): Collection
    {
        $category->loadMissing([
            'menuTabs' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('id'),
        ]);

        return $category->distinctActiveMenuTabs('tr')->pluck('id');
    }

    /**
     * @return Collection<int, Product>
     */
    public static function productsForTab(int $tabId): Collection
    {
        $productIds = collect();

        try {
            if (Schema::hasColumn('products', 'menu_tab_id')) {
                $productIds = $productIds->merge(
                    Product::query()->where('menu_tab_id', $tabId)->pluck('id')
                );
            }

            if (Schema::hasTable('menu_sections') && Schema::hasColumn('products', 'menu_section_id')) {
                $sectionIds = MenuSection::query()->where('menu_tab_id', $tabId)->pluck('id');
                if ($sectionIds->isNotEmpty()) {
                    $productIds = $productIds->merge(
                        Product::query()->whereIn('menu_section_id', $sectionIds)->pluck('id')
                    );
                }
            }
        } catch (Throwable) {
            return collect();
        }

        if ($productIds->isEmpty()) {
            return collect();
        }

        return Product::query()
            ->whereIn('id', $productIds->unique()->values())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Product>
     */
    public static function blockingProductsForTab(int $tabId): Collection
    {
        return self::productsForTab($tabId);
    }

    public static function countTabProducts(int $tabId): int
    {
        return self::productsForTab($tabId)->count();
    }

    public static function countSectionProducts(int $sectionId): int
    {
        if (! Schema::hasColumn('products', 'menu_section_id')) {
            return 0;
        }

        try {
            return (int) Product::query()->where('menu_section_id', $sectionId)->count();
        } catch (Throwable) {
            return 0;
        }
    }
}
