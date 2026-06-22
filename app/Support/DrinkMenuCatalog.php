<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * İçecek kategorisi için sekme + alt bölüm hiyerarşisi.
 * Ürün adı/açıklamasına göre sınıflandırır; admin'den eklenen ürünler otomatik gruplanır.
 */
class DrinkMenuCatalog
{
    /** @var array<string, array{label: array<string, string>, keywords: string[]}> */
    private const TABS = [
        'all' => [
            'label' => ['tr' => 'Tüm İçecekler', 'en' => 'All Drinks', 'ru' => 'Все напитки'],
            'keywords' => [],
        ],
        'coffee' => [
            'label' => ['tr' => 'Kahveler', 'en' => 'Coffee', 'ru' => 'Кофе'],
            'keywords' => [
                'espresso', 'latte', 'cappuccino', 'americano', 'mocha', 'macchiato',
                'flat white', 'cortado', 'kahve', 'filtre', 'cold brew', 'frappe', 'frappé',
                'buzlu kahve', 'iced coffee', 'ristretto', 'lungo', 'salep', 'çikolata',
                'chai', 'toffee', 'caramel', 'biscoff', 'oat', 'yulaf', 'harmony', 'red eye',
                'black eye', 'dead eye', 'misto', 'boomer', 'kıtkat', 'kitkat', 'tiramisu',
                'maple', 'fıstık', 'fındık', 'pistachio', 'hazelnut',
            ],
        ],
        'tea' => [
            'label' => ['tr' => 'Çaylar', 'en' => 'Tea', 'ru' => 'Чай'],
            'keywords' => [
                'çay', 'cay', 'tea', 'chai', 'matcha', 'bitki', 'herbal', 'yeşil çay', 'green tea',
            ],
        ],
        'soft' => [
            'label' => ['tr' => 'Meşrubatlar', 'en' => 'Soft Drinks', 'ru' => 'Безалкогольные'],
            'keywords' => [
                'limonata', 'lemonade', 'soda', 'cola', 'meyve suyu', 'juice', 'su ', 'water',
                'ayran', 'smoothie', 'shake', 'milkshake', 'portakal', 'orange',
            ],
        ],
        'cocktail' => [
            'label' => ['tr' => 'Kokteyller', 'en' => 'Cocktails', 'ru' => 'Коктейли'],
            'keywords' => [
                'mojito', 'kokteyl', 'cocktail', 'mocktail', 'spritz', 'tonic', 'n/a',
            ],
        ],
    ];

    /** @var array<string, array{title: array<string, string>, tab: string, keywords: string[]}> */
    private const SECTIONS = [
        'espresso-based' => [
            'title' => ['tr' => 'Espresso Bazlılar', 'en' => 'Espresso Based', 'ru' => 'На эспрессо'],
            'tab' => 'coffee',
            'keywords' => ['espresso', 'americano', 'ristretto', 'lungo', 'doppio'],
        ],
        'milk-coffee' => [
            'title' => ['tr' => 'Sütlü Kahveler', 'en' => 'Milk Coffees', 'ru' => 'С молоком'],
            'tab' => 'coffee',
            'keywords' => ['latte', 'cappuccino', 'flat white', 'mocha', 'macchiato', 'cortado', 'latte'],
        ],
        'cold-coffee' => [
            'title' => ['tr' => 'Soğuk Kahveler', 'en' => 'Iced Coffee', 'ru' => 'Холодный кофе'],
            'tab' => 'coffee',
            'keywords' => ['buzlu', 'iced', 'cold brew', 'frappe', 'frappé', 'soğuk kahve'],
        ],
        'filter-coffee' => [
            'title' => ['tr' => 'Filtre & Demleme', 'en' => 'Filter & Brew', 'ru' => 'Фильтр'],
            'tab' => 'coffee',
            'keywords' => ['filtre', 'filter', 'v60', 'chemex', 'french press', 'demleme'],
        ],
        'hot-tea' => [
            'title' => ['tr' => 'Sıcak Çaylar', 'en' => 'Hot Tea', 'ru' => 'Горячий чай'],
            'tab' => 'tea',
            'keywords' => ['çay', 'cay', 'tea', 'chai', 'matcha', 'bitki', 'herbal'],
        ],
        'iced-tea' => [
            'title' => ['tr' => 'Soğuk Çaylar', 'en' => 'Iced Tea', 'ru' => 'Холодный чай'],
            'tab' => 'tea',
            'keywords' => ['buzlu çay', 'iced tea', 'soğuk çay'],
        ],
        'fresh-drinks' => [
            'title' => ['tr' => 'Taze Sıkılmış', 'en' => 'Fresh Pressed', 'ru' => 'Свежевыжатые'],
            'tab' => 'soft',
            'keywords' => ['limonata', 'lemonade', 'portakal', 'orange', 'meyve suyu', 'juice'],
        ],
        'sparkling' => [
            'title' => ['tr' => 'Gazlı & Soğuk', 'en' => 'Sparkling & Cold', 'ru' => 'Газированные'],
            'tab' => 'soft',
            'keywords' => ['soda', 'cola', 'tonic', 'su ', 'water', 'ayran', 'smoothie', 'shake'],
        ],
        'signatures' => [
            'title' => ['tr' => 'İmza Kokteyller', 'en' => 'Signature Cocktails', 'ru' => 'Фирменные коктейли'],
            'tab' => 'cocktail',
            'keywords' => ['mojito', 'kokteyl', 'cocktail', 'mocktail', 'spritz'],
        ],
        'hot-special' => [
            'title' => ['tr' => 'Sıcak Özel', 'en' => 'Hot Specials', 'ru' => 'Горячие'],
            'tab' => 'coffee',
            'keywords' => ['salep', 'sıcak çikolata', 'hot chocolate', 'white hot chocolate'],
        ],
        'vegan-drinks' => [
            'title' => ['tr' => 'Vegan İçecekler', 'en' => 'Vegan Drinks', 'ru' => 'Веган'],
            'tab' => 'coffee',
            'keywords' => ['oat latte', 'yulaf', 'harmony latte', 'vegan', 'roasted almond'],
        ],
        'frappe-coffee' => [
            'title' => ['tr' => 'Kahve Bazlı Frappe', 'en' => 'Coffee Frappes', 'ru' => 'Кофейный фrappe'],
            'tab' => 'coffee',
            'keywords' => ['coffee frappe', 'mocha frappe', 'caramel frappe', 'hazelnut frappe', 'kahve frappe'],
        ],
        'frappe-cream' => [
            'title' => ['tr' => 'Cream Frappe', 'en' => 'Cream Frappes', 'ru' => 'Cream frappe'],
            'tab' => 'coffee',
            'keywords' => ['cream frappe', 'strawberry cream', 'chocolate cream', 'pistachio cream', 'caramel cream frappe'],
        ],
        'other' => [
            'title' => ['tr' => 'Diğer', 'en' => 'Other', 'ru' => 'Другое'],
            'tab' => 'all',
            'keywords' => [],
        ],
    ];

    /** @var array<string, array{title: array<string, string>, tab: string}> */
    private const OTHER_SECTIONS = [
        'other-coffee' => [
            'title' => ['tr' => 'Diğer Kahveler', 'en' => 'More Coffee', 'ru' => 'Ещё кофе'],
            'tab' => 'coffee',
        ],
        'other-tea' => [
            'title' => ['tr' => 'Diğer Çaylar', 'en' => 'More Tea', 'ru' => 'Ещё чай'],
            'tab' => 'tea',
        ],
        'other-soft' => [
            'title' => ['tr' => 'Diğer Meşrubatlar', 'en' => 'More Soft Drinks', 'ru' => 'Ещё напитки'],
            'tab' => 'soft',
        ],
        'other-cocktail' => [
            'title' => ['tr' => 'Diğer Kokteyller', 'en' => 'More Cocktails', 'ru' => 'Ещё коктейли'],
            'tab' => 'cocktail',
        ],
    ];

    /**
     * @param  Collection<int, Product>  $products
     * @return array{tabs: list<array{id: string, label: string}>, sections: list<array{id: string, title: string, tab: string, products: Collection<int, Product>}>}
     */
    public static function build(Collection $products, string $locale = 'tr'): array
    {
        $classified = $products->map(function (Product $product) use ($locale) {
            $haystack = self::productHaystack($product, $locale);
            $tab = self::resolveTab($haystack);
            $section = self::resolveSection($haystack, $tab);

            if ($section === 'other') {
                $section = 'other-'.$tab;
            }

            return [
                'product' => $product,
                'tab' => $tab,
                'section' => $section,
            ];
        });

        $tabs = collect(self::TABS)
            ->map(fn (array $def, string $id) => [
                'id' => $id,
                'label' => $def['label'][$locale] ?? $def['label']['tr'],
            ])
            ->values()
            ->all();

        $sectionOrder = array_merge(array_keys(self::SECTIONS), array_keys(self::OTHER_SECTIONS));
        $grouped = $classified->groupBy('section');

        $sections = collect($sectionOrder)
            ->map(function (string $sectionId) use ($grouped, $locale) {
                $items = $grouped->get($sectionId, collect());
                if ($items->isEmpty()) {
                    return null;
                }

                $def = self::SECTIONS[$sectionId] ?? self::OTHER_SECTIONS[$sectionId] ?? null;
                if (! $def) {
                    return null;
                }

                return [
                    'id' => $sectionId,
                    'title' => $def['title'][$locale] ?? $def['title']['tr'],
                    'tab' => $def['tab'],
                    'products' => $items->map(fn (array $item) => [
                        'product' => $item['product'],
                        'tab' => $item['tab'],
                    ])->values(),
                ];
            })
            ->filter()
            ->values()
            ->all();

        return [
            'tabs' => $tabs,
            'sections' => $sections,
        ];
    }

    private static function productHaystack(Product $product, string $locale): string
    {
        $names = array_filter([
            $product->getTranslation('name', 'tr', false),
            $product->getTranslation('name', 'en', false),
            $product->localizedName($locale),
            $product->getTranslation('description', 'tr', false),
            $product->getTranslation('description', 'en', false),
            $product->localizedDescription($locale),
        ]);

        return mb_strtolower(implode(' ', $names), 'UTF-8');
    }

    private static function resolveTab(string $haystack): string
    {
        foreach (['cocktail', 'tea', 'coffee', 'soft'] as $tabId) {
            if (self::matchesKeywords($haystack, self::TABS[$tabId]['keywords'])) {
                return $tabId;
            }
        }

        return 'soft';
    }

    private static function resolveSection(string $haystack, string $tab): string
    {
        foreach (self::SECTIONS as $sectionId => $def) {
            if ($sectionId === 'other') {
                continue;
            }
            if (($def['tab'] ?? 'all') !== $tab && $def['tab'] !== 'all') {
                continue;
            }
            if (self::matchesKeywords($haystack, $def['keywords'])) {
                return $sectionId;
            }
        }

        foreach (self::SECTIONS as $sectionId => $def) {
            if ($def['tab'] === $tab && $sectionId !== 'other') {
                if (self::matchesKeywords($haystack, $def['keywords'])) {
                    return $sectionId;
                }
            }
        }

        return 'other';
    }

    /** @param  string[]  $keywords */
    private static function matchesKeywords(string $haystack, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            $keyword = mb_strtolower(trim($keyword), 'UTF-8');
            if ($keyword === '') {
                continue;
            }
            if (str_contains($haystack, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
