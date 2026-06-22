<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CafeGallery;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use App\Models\Restaurant;
use App\Models\Setting;
use App\Models\Table;
use App\Models\User;
use App\Support\CurrentRestaurant;
use App\Support\DrinkOptionTemplates;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class HumanSeeder extends Seeder
{
    public function run(): void
    {
        $restaurant = Restaurant::updateOrCreate(
            ['slug' => 'human'],
            [
                'name' => 'Human Cafe',
                'kitchen_token' => env('KITCHEN_KIOSK_TOKEN', Str::random(48)),
                'is_active' => true,
            ],
        );

        CurrentRestaurant::run($restaurant, function () use ($restaurant) {
            User::updateOrCreate(
                ['email' => 'admin@human.com'],
                [
                    'name' => 'Human Admin',
                    'password' => Hash::make('human2026'),
                    'role' => User::ROLE_ADMIN,
                    'restaurant_id' => $restaurant->id,
                ]
            );

            User::updateOrCreate(
                ['email' => 'garson@human.com'],
                [
                    'name' => 'Garson',
                    'password' => Hash::make('human2026'),
                    'role' => User::ROLE_WAITER,
                    'restaurant_id' => $restaurant->id,
                ]
            );

            User::updateOrCreate(
                ['email' => 'kasa@human.com'],
                [
                    'name' => 'Kasa',
                    'password' => Hash::make('human2026'),
                    'role' => User::ROLE_CASHIER,
                    'restaurant_id' => $restaurant->id,
                ]
            );

            $defaults = [
                'venue_name' => 'Human Cafe',
                'venue_slogan' => 'Human Social People',
                'brand_mark' => 'Human Cafe',
                'venue_tagline' => 'Human Social People',
                'venue_phone' => '+90 555 000 00 00',
                'venue_address' => 'İstanbul',
                'currency' => '₺',
                'order_enabled' => '1',
                'display_interval' => '10',
                'daily_motto' => 'İyi insanlar, iyi sohbetler.',
                'wifi_password' => 'HumanSocial2026',
                'show_motto_banner' => '1',
                'show_wifi_banner' => '1',
                'spotify_url' => 'https://open.spotify.com/playlist/37i9dQZF1DX0XUsuxWHRQd',
                'spotify_title' => 'HSP Vibes',
                'instagram_url' => 'https://www.instagram.com/ramaznkra/',
                'instagram_handle' => '@ramaznkra',
            ];
            foreach ($defaults as $key => $value) {
                Setting::set($key, $value);
            }

            $categories = [
                ['name' => ['tr' => 'Yiyecek', 'en' => 'Food', 'ru' => 'Еда'], 'slug' => 'yiyecek', 'type' => 'kitchen', 'image' => 'images/categories/photos/yiyecek.jpg', 'sort_order' => 1],
                ['name' => ['tr' => 'İçecek', 'en' => 'Drinks', 'ru' => 'Напитки'], 'slug' => 'icecek', 'type' => 'bar', 'image' => 'images/categories/photos/icecek.jpg', 'sort_order' => 2],
            ];

            foreach ($categories as $cat) {
                Category::updateOrCreate(
                    ['restaurant_id' => $restaurant->id, 'slug' => $cat['slug']],
                    $cat + ['is_active' => true, 'icon' => null],
                );
            }

            Category::query()
                ->where('restaurant_id', $restaurant->id)
                ->whereNotIn('slug', ['yiyecek', 'icecek'])
                ->update(['is_active' => false]);

            $spottedCards = [
                [
                    'image_path' => 'images/menu/slider/misafir-1.jpg',
                    'title' => 'Human Ailesi',
                    'description' => 'Sevgili Cem Yılmaz, imza kahvemizi deneyimlerken… #SocialMoments',
                    'badge_text' => 'Spotted at HSP ✨',
                    'sort_order' => 1,
                ],
                [
                    'image_path' => 'images/menu/slider/mekan-1.jpg',
                    'title' => 'Lounge Atmosferi',
                    'description' => 'Sosyal sohbetlerin ve iyi insanların buluşma noktası.',
                    'badge_text' => 'HSP Moments',
                    'sort_order' => 2,
                ],
                [
                    'image_path' => 'images/menu/slider/misafir-2.jpg',
                    'title' => null,
                    'description' => 'Bugün telefonları bir kenara bırakıp masadakiyle konuşanlara selam olsun.',
                    'badge_text' => 'Spotted at HSP ✨',
                    'sort_order' => 3,
                ],
            ];

            foreach ($spottedCards as $card) {
                CafeGallery::updateOrCreate(
                    ['restaurant_id' => $restaurant->id, 'image_path' => $card['image_path']],
                    $card + ['is_active' => true],
                );
            }

            $products = [
                ['category' => 'yiyecek', 'name' => ['tr' => 'Human Burger', 'en' => 'Human Burger', 'ru' => 'Бургер Human'], 'description' => ['tr' => 'Özel soslu, cheddar peynirli burger', 'en' => 'Signature sauce and cheddar burger', 'ru' => 'Фирменный соус и сыр чеддер'], 'price' => 320, 'badge' => 'Popüler'],
                ['category' => 'yiyecek', 'name' => ['tr' => 'Sosyal Tabağı', 'en' => 'Social Platter', 'ru' => 'Социальная тарелка'], 'description' => ['tr' => 'Paylaşımlık atıştırmalık tabağı', 'en' => 'Sharing snack platter', 'ru' => 'Закуски для компании'], 'price' => 280],
                ['category' => 'yiyecek', 'name' => ['tr' => 'Nachos', 'en' => 'Nachos', 'ru' => 'Начос'], 'description' => ['tr' => 'Guacamole ve salsa ile', 'en' => 'With guacamole and salsa', 'ru' => 'С гуакамоле и сальсой'], 'price' => 195],
            ];

            $sort = 0;
            foreach ($products as $p) {
                $cat = Category::where('slug', $p['category'])->first();
                Product::updateOrCreate(
                    [
                        'restaurant_id' => $restaurant->id,
                        'category_id' => $cat->id,
                        'name->tr' => $p['name']['tr'],
                    ],
                    [
                        'type' => $cat?->type ?? 'kitchen',
                        'name' => $p['name'],
                        'description' => $p['description'],
                        'price' => $p['price'],
                        'badge' => $p['badge'] ?? null,
                        'sort_order' => $sort++,
                        'is_available' => true,
                    ]
                );
            }

            $this->seedCoffeeDeMadridDrinks($restaurant);
            $this->seedProductOptions($restaurant);

            (new MenuHierarchySeeder)->run($restaurant);

            for ($i = 1; $i <= 8; $i++) {
                Table::updateOrCreate(
                    ['restaurant_id' => $restaurant->id, 'number' => (string) $i],
                    ['qr_token' => Str::random(16), 'is_active' => true]
                );
            }
        });
    }

    private function seedCoffeeDeMadridDrinks(Restaurant $restaurant): void
    {
        $icecek = Category::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('slug', 'icecek')
            ->first();

        if (! $icecek) {
            return;
        }

        /** @var list<array<string, mixed>> $drinks */
        $drinks = require database_path('data/coffee-de-madrid-drinks.php');
        $allowedNames = collect($drinks)->pluck('name')->all();

        Product::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('category_id', $icecek->id)
            ->whereNotIn('name->tr', $allowedNames)
            ->delete();

        Product::query()
            ->where('restaurant_id', $restaurant->id)
            ->whereHas('category', fn ($q) => $q->whereNotIn('slug', ['yiyecek', 'icecek']))
            ->delete();

        foreach ($drinks as $index => $drink) {
            $trName = $drink['name'];
            $description = isset($drink['desc'])
                ? ['tr' => $drink['desc'], 'en' => $drink['desc'], 'ru' => $drink['desc']]
                : null;

            Product::updateOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'category_id' => $icecek->id,
                    'name->tr' => $trName,
                ],
                [
                    'type' => 'bar',
                    'name' => ['tr' => $trName, 'en' => $trName, 'ru' => $trName],
                    'description' => $description,
                    'price' => $drink['price'],
                    'badge' => $drink['code'] ?? null,
                    'sort_order' => 100 + $index,
                    'is_available' => true,
                    'in_stock' => true,
                ]
            );
        }
    }

    private function syncDrinkOptionGroups(Product $product, array $groups): void
    {
        foreach ($groups as $sort => $groupDef) {
            $group = ProductOptionGroup::updateOrCreate(
                ['product_id' => $product->id, 'name->tr' => $groupDef['name']['tr']],
                [
                    'restaurant_id' => $product->restaurant_id,
                    'name' => $groupDef['name'],
                    'type' => $groupDef['type'],
                    'required' => $groupDef['required'],
                    'sort_order' => $sort + 1,
                ],
            );

            foreach ($groupDef['options'] as $optSort => $optDef) {
                ProductOption::updateOrCreate(
                    ['product_option_group_id' => $group->id, 'name->tr' => $optDef['name']['tr']],
                    [
                        'name' => $optDef['name'],
                        'price_modifier' => $optDef['price'],
                        'is_default' => $optDef['default'] ?? false,
                        'sort_order' => $optSort + 1,
                        'is_active' => true,
                    ],
                );
            }
        }
    }

    private function seedProductOptions(Restaurant $restaurant): void
    {
        $burger = Product::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('name->tr', 'Human Burger')
            ->first();

        if ($burger) {
            $sizeGroup = ProductOptionGroup::updateOrCreate(
                ['product_id' => $burger->id, 'name->tr' => 'Boy'],
                [
                    'restaurant_id' => $restaurant->id,
                    'name' => ['tr' => 'Boy', 'en' => 'Size', 'ru' => 'Размер'],
                    'type' => ProductOptionGroup::TYPE_SINGLE,
                    'required' => true,
                    'sort_order' => 1,
                ],
            );

            $extrasGroup = ProductOptionGroup::updateOrCreate(
                ['product_id' => $burger->id, 'name->tr' => 'Ekstralar'],
                [
                    'restaurant_id' => $restaurant->id,
                    'name' => ['tr' => 'Ekstralar', 'en' => 'Extras', 'ru' => 'Дополнения'],
                    'type' => ProductOptionGroup::TYPE_MULTIPLE,
                    'required' => false,
                    'sort_order' => 2,
                ],
            );

            foreach ([
                ['tr' => 'Normal', 'en' => 'Regular', 'ru' => 'Обычный', 'price' => 0, 'default' => true, 'sort' => 1],
                ['tr' => 'Büyük Boy', 'en' => 'Large', 'ru' => 'Большой', 'price' => 40, 'default' => false, 'sort' => 2],
            ] as $opt) {
                ProductOption::updateOrCreate(
                    ['product_option_group_id' => $sizeGroup->id, 'name->tr' => $opt['tr']],
                    [
                        'name' => ['tr' => $opt['tr'], 'en' => $opt['en'], 'ru' => $opt['ru']],
                        'price_modifier' => $opt['price'],
                        'is_default' => $opt['default'],
                        'sort_order' => $opt['sort'],
                    ],
                );
            }

            foreach ([
                ['tr' => 'Ekstra Cheddar', 'en' => 'Extra Cheddar', 'ru' => 'Доп. чеддер', 'price' => 35, 'sort' => 1],
                ['tr' => 'Ekstra Sos', 'en' => 'Extra Sauce', 'ru' => 'Доп. соус', 'price' => 15, 'sort' => 2],
                ['tr' => 'Jalapeño', 'en' => 'Jalapeño', 'ru' => 'Халапеньо', 'price' => 20, 'sort' => 3],
            ] as $opt) {
                ProductOption::updateOrCreate(
                    ['product_option_group_id' => $extrasGroup->id, 'name->tr' => $opt['tr']],
                    [
                        'name' => ['tr' => $opt['tr'], 'en' => $opt['en'], 'ru' => $opt['ru']],
                        'price_modifier' => $opt['price'],
                        'is_default' => false,
                        'sort_order' => $opt['sort'],
                    ],
                );
            }
        }

        /** @var list<array<string, mixed>> $drinks */
        $drinks = require database_path('data/coffee-de-madrid-drinks.php');
        $variationNames = collect($drinks)
            ->filter(fn ($d) => ! empty($d['variations']))
            ->pluck('name')
            ->all();

        Product::query()
            ->where('restaurant_id', $restaurant->id)
            ->whereIn('name->tr', $variationNames)
            ->each(fn (Product $product) => $this->syncDrinkOptionGroups(
                $product,
                DrinkOptionTemplates::fullDrinkPackage(),
            ));
    }
}
