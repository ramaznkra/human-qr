<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\MenuSection;
use App\Models\MenuTab;
use App\Models\Product;
use App\Models\Restaurant;
use App\Support\CurrentRestaurant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MenuHierarchySeeder extends Seeder
{
    /** @var array<string, array{tr: string, en: string, ru: string}> */
    private const SECTION_LABELS = [
        'espresso-based' => ['tr' => 'Espresso Bazlılar', 'en' => 'Espresso Based', 'ru' => 'На эспрессо'],
        'filter-coffee' => ['tr' => 'Filtre Kahveler', 'en' => 'Filter Coffee', 'ru' => 'Фильтр-кофе'],
        'milk-coffee' => ['tr' => 'Sütlü Kahveler', 'en' => 'Milk Coffees', 'ru' => 'С молоком'],
        'hot-special' => ['tr' => 'Sıcak Özel', 'en' => 'Hot Specials', 'ru' => 'Горячие'],
        'vegan-drinks' => ['tr' => 'Vegan İçecekler', 'en' => 'Vegan Drinks', 'ru' => 'Веган'],
        'frappe-coffee' => ['tr' => 'Kahve Bazlı Frappe', 'en' => 'Coffee Frappes', 'ru' => 'Кофейный frappe'],
        'frappe-cream' => ['tr' => 'Cream Frappe', 'en' => 'Cream Frappes', 'ru' => 'Cream frappe'],
        'food-main' => ['tr' => 'Ana Yemekler', 'en' => 'Main Dishes', 'ru' => 'Основные блюда'],
    ];

    public function run(?Restaurant $restaurant = null): void
    {
        $restaurant ??= Restaurant::query()->where('slug', 'human')->first();

        if (! $restaurant) {
            return;
        }

        CurrentRestaurant::run($restaurant, function () use ($restaurant) {
            $this->seedIcecekHierarchy($restaurant);
            $this->seedYiyecekHierarchy($restaurant);
            $this->assignProductsToSections($restaurant);
        });
    }

    private function seedIcecekHierarchy(Restaurant $restaurant): void
    {
        $icecek = Category::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('slug', 'icecek')
            ->first();

        if (! $icecek) {
            return;
        }

        $tab = MenuTab::updateOrCreate(
            [
                'restaurant_id' => $restaurant->id,
                'category_id' => $icecek->id,
                'slug' => 'kahveler',
            ],
            [
                'name' => ['tr' => 'Kahveler', 'en' => 'Coffee', 'ru' => 'Кофе'],
                'layout' => MenuTab::LAYOUT_GROUPED,
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        $sectionOrder = [
            'espresso-based',
            'filter-coffee',
            'milk-coffee',
            'hot-special',
            'vegan-drinks',
            'frappe-coffee',
            'frappe-cream',
        ];

        foreach ($sectionOrder as $i => $slug) {
            $labels = self::SECTION_LABELS[$slug];

            MenuSection::updateOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'menu_tab_id' => $tab->id,
                    'slug' => $slug,
                ],
                [
                    'name' => $labels,
                    'sort_order' => $i + 1,
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedYiyecekHierarchy(Restaurant $restaurant): void
    {
        $yiyecek = Category::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('slug', 'yiyecek')
            ->first();

        if (! $yiyecek) {
            return;
        }

        $tab = MenuTab::updateOrCreate(
            [
                'restaurant_id' => $restaurant->id,
                'category_id' => $yiyecek->id,
                'slug' => 'menu',
            ],
            [
                'name' => ['tr' => 'Menü', 'en' => 'Menu', 'ru' => 'Меню'],
                'layout' => MenuTab::LAYOUT_GROUPED,
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        MenuSection::updateOrCreate(
            [
                'restaurant_id' => $restaurant->id,
                'menu_tab_id' => $tab->id,
                'slug' => 'food-main',
            ],
            [
                'name' => self::SECTION_LABELS['food-main'],
                'sort_order' => 1,
                'is_active' => true,
            ],
        );
    }

    private function assignProductsToSections(Restaurant $restaurant): void
    {
        /** @var list<array<string, mixed>> $drinks */
        $drinks = require database_path('data/coffee-de-madrid-drinks.php');

        $icecek = Category::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('slug', 'icecek')
            ->first();

        if ($icecek) {
            $sections = MenuSection::query()
                ->where('restaurant_id', $restaurant->id)
                ->whereHas('menuTab', fn ($q) => $q->where('category_id', $icecek->id))
                ->get()
                ->keyBy('slug');

            foreach ($drinks as $drink) {
                $sectionSlug = $drink['section'] ?? 'milk-coffee';
                $section = $sections->get($sectionSlug);

                Product::query()
                    ->where('restaurant_id', $restaurant->id)
                    ->where('category_id', $icecek->id)
                    ->where('name->tr', $drink['name'])
                    ->update(['menu_section_id' => $section?->id]);
            }
        }

        $yiyecek = Category::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('slug', 'yiyecek')
            ->first();

        if ($yiyecek) {
            $section = MenuSection::query()
                ->where('restaurant_id', $restaurant->id)
                ->where('slug', 'food-main')
                ->whereHas('menuTab', fn ($q) => $q->where('category_id', $yiyecek->id))
                ->first();

            if ($section) {
                Product::query()
                    ->where('restaurant_id', $restaurant->id)
                    ->where('category_id', $yiyecek->id)
                    ->update(['menu_section_id' => $section->id]);
            }
        }
    }
}
