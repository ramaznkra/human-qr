<?php

namespace App\Services;

use App\Models\Category;
use App\Models\MenuTab;
use App\Models\OrderItem;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MenuCatalogService
{
    /**
     * @return array{categories: Collection<int, Category>, settings: array<string, mixed>, productPopularity: \Illuminate\Support\Collection<int|string, mixed>}
     */
    public function load(string $locale): array
    {
        $tabEagerLoad = [
            'directProducts' => fn ($q) => $q->available()->with([
                'optionGroups' => fn ($q) => $q->orderBy('sort_order'),
                'optionGroups.options' => fn ($q) => $q->orderBy('sort_order'),
            ]),
            'menuSections' => fn ($q) => $q->active()->with([
                'products' => fn ($q) => $q->available()->with([
                    'optionGroups' => fn ($q) => $q->orderBy('sort_order'),
                    'optionGroups.options' => fn ($q) => $q->orderBy('sort_order'),
                ]),
            ]),
        ];

        $categories = Category::active()
            ->with([
                'menuTabs' => fn ($q) => $q->active()->orderBy('sort_order')->orderBy('id')->with($tabEagerLoad),
                'products' => fn ($q) => $q->available()->with([
                    'optionGroups' => fn ($q) => $q->orderBy('sort_order'),
                    'optionGroups.options' => fn ($q) => $q->orderBy('sort_order'),
                ]),
            ])
            ->get();

        $categories->each(function (Category $category) use ($tabEagerLoad) {
            $tabs = $category->distinctActiveMenuTabs();

            if ($tabs->isEmpty()) {
                $category->setRelation('menuTabs', $tabs);

                return;
            }

            MenuTab::query()
                ->whereIn('id', $tabs->pluck('id'))
                ->with($tabEagerLoad)
                ->get()
                ->each(function (MenuTab $loadedTab) use ($tabs) {
                    $match = $tabs->firstWhere('id', $loadedTab->id);
                    if ($match) {
                        $match->setRelation('directProducts', $loadedTab->directProducts);
                        $match->setRelation('menuSections', $loadedTab->menuSections);
                    }
                });

            $category->setRelation('menuTabs', $tabs);
        });

        $productPopularity = OrderItem::query()
            ->whereHas('order', fn ($q) => $q->whereDate('created_at', today()))
            ->whereNotNull('product_id')
            ->select('order_items.product_id', DB::raw('SUM(order_items.quantity) as total_qty'))
            ->groupBy('order_items.product_id')
            ->pluck('total_qty', 'product_id');

        return [
            'categories' => $categories,
            'settings' => Setting::allCached(),
            'productPopularity' => $productPopularity,
        ];
    }
}
