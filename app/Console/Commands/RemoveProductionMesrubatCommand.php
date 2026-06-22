<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\MenuSection;
use App\Models\MenuTab;
use App\Models\Product;
use App\Models\Restaurant;
use App\Support\CurrentRestaurant;
use App\Support\MenuHierarchyCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RemoveProductionMesrubatCommand extends Command
{
    protected $signature = 'menu:remove-production-mesrubat
                            {--restaurant=human : Restoran slug}
                            {--dry-run : Önizleme — silme yapmaz}
                            {--remove-tabs : Boş kalan Meşrubat sekmelerini de sil}
                            {--purge-all : Meşrubat sekmelerindeki TÜM ürünleri sil ve sekmeleri kaldır}';

    protected $description = 'Meşrubat seeder/grup verisini kaldırır; --purge-all ile sekme tamamen temizlenir (Kahveler dokunulmaz)';

    /** @var list<string> */
    private const SECTION_SLUGS = [
        'su-mineralliler',
        'gazli-icecekler',
        'soguk-caylar',
        'meyve-sulari-geleneksel',
    ];

    /** @var list<string> */
    private const PRODUCT_NAMES = [
        'Cam Su (330ml)',
        'Cam Su (750ml)',
        'Doğal Maden Suyu',
        'Churchill',
        'Meyveli Maden Suyu',
        'Coca-Cola',
        'Coca-Cola Zero',
        'Sprite',
        'Fanta',
        'Schweppes Tonik',
        'Red Bull Enerji İçeceği',
        'Ice Tea Şeftali',
        'Ice Tea Limon',
        'Ice Tea Mango',
        'Karışık Meyve Suyu',
        'Vişne Suyu',
        'Şeftali Suyu',
        'Cam Şişe Ayran',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry-run — veritabanı değiştirilmeyecek.');
        }

        $restaurant = Restaurant::query()
            ->where('slug', $this->option('restaurant'))
            ->first();

        if (! $restaurant) {
            $this->error('Restoran bulunamadı.');

            return self::FAILURE;
        }

        return CurrentRestaurant::run($restaurant, function () use ($restaurant, $dryRun) {
            $icecek = Category::query()
                ->where('restaurant_id', $restaurant->id)
                ->where('slug', 'icecek')
                ->first();

            if (! $icecek) {
                $this->error('İçecek kategorisi bulunamadı.');

                return self::FAILURE;
            }

            $mesrubatTabs = MenuTab::query()
                ->where('category_id', $icecek->id)
                ->get()
                ->filter(fn (MenuTab $tab) => $this->isMesrubatTab($tab));

            $this->info('Meşrubat sekmeleri: '.$mesrubatTabs->pluck('id')->join(', ') ?: 'yok');

            if ($this->option('purge-all')) {
                return $this->purgeMesrubatTabs($mesrubatTabs, $dryRun);
            }

            $products = Product::query()
                ->where('restaurant_id', $restaurant->id)
                ->where('category_id', $icecek->id)
                ->whereIn('name->tr', self::PRODUCT_NAMES)
                ->get();

            $this->line("Silinecek ürün: {$products->count()}");
            foreach ($products as $product) {
                $this->line("  - {$product->getTranslation('name', 'tr')} (#{$product->id})");
            }

            if (! $dryRun) {
                Product::query()
                    ->whereIn('id', $products->pluck('id'))
                    ->delete();
            }

            $sectionQuery = MenuSection::query()
                ->where('restaurant_id', $restaurant->id)
                ->whereIn('slug', self::SECTION_SLUGS);

            if ($mesrubatTabs->isNotEmpty()) {
                $sectionQuery->whereIn('menu_tab_id', $mesrubatTabs->pluck('id'));
            }

            $sections = $sectionQuery->get();
            $this->line("Silinecek grup: {$sections->count()}");
            foreach ($sections as $section) {
                $this->line("  - {$section->getTranslation('name', 'tr')} (#{$section->id})");
            }

            if (! $dryRun) {
                MenuSection::query()->whereIn('id', $sections->pluck('id'))->delete();
            }

            if ($this->option('remove-tabs')) {
                foreach ($mesrubatTabs as $tab) {
                    $hasProducts = Product::query()
                        ->where('menu_tab_id', $tab->id)
                        ->exists();

                    $hasSectionProducts = MenuSection::query()
                        ->where('menu_tab_id', $tab->id)
                        ->whereHas('products')
                        ->exists();

                    if ($hasProducts || $hasSectionProducts) {
                        $this->warn("Sekme #{$tab->id} atlandı — hâlâ ürün var.");

                        continue;
                    }

                    $this->line("Silinecek sekme: {$tab->getTranslation('name', 'tr')} (#{$tab->id})");

                    if (! $dryRun) {
                        MenuSection::query()->where('menu_tab_id', $tab->id)->delete();
                        $tab->delete();
                    }
                }
            }

            $this->newLine();
            $this->info($dryRun ? 'Dry-run tamamlandı.' : 'Production meşrubat verisi kaldırıldı.');

            return self::SUCCESS;
        });
    }

    private function isMesrubatTab(MenuTab $tab): bool
    {
        if ($this->isKahvelerTab($tab)) {
            return false;
        }

        $name = Str::lower(Str::ascii(trim($tab->getTranslation('name', 'tr'))));
        $slug = Str::lower(trim($tab->slug ?? ''));

        return str_contains($name, 'mesrubat') || str_contains($slug, 'mesrubat');
    }

    private function isKahvelerTab(MenuTab $tab): bool
    {
        $name = Str::lower(Str::ascii(trim($tab->getTranslation('name', 'tr'))));
        $slug = Str::lower(trim($tab->slug ?? ''));

        return str_contains($name, 'kahve') || $slug === 'kahveler' || str_contains($slug, 'kahve');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, MenuTab>  $mesrubatTabs
     */
    private function purgeMesrubatTabs($mesrubatTabs, bool $dryRun): int
    {
        foreach ($mesrubatTabs as $tab) {
            $products = MenuHierarchyCatalog::productsForTab($tab->id);
            $this->line("Sekme #{$tab->id} «{$tab->getTranslation('name', 'tr')}» — {$products->count()} ürün silinecek");
            foreach ($products as $product) {
                $avail = $product->is_available ? 'açık' : 'kapalı';
                $this->line("  - #{$product->id} {$product->getTranslation('name', 'tr')} ({$avail})");
            }

            if (! $dryRun && $products->isNotEmpty()) {
                Product::query()->whereIn('id', $products->pluck('id'))->delete();
            }

            $sections = MenuSection::query()->where('menu_tab_id', $tab->id)->get();
            foreach ($sections as $section) {
                $this->line("  - grup siliniyor: #{$section->id} {$section->getTranslation('name', 'tr')}");
            }

            if (! $dryRun) {
                MenuSection::query()->where('menu_tab_id', $tab->id)->delete();
                $tab->delete();
            }
        }

        $this->newLine();
        $this->info($dryRun ? 'Dry-run tamamlandı.' : 'Meşrubat sekmeleri ve ürünleri kaldırıldı.');

        return self::SUCCESS;
    }
}
