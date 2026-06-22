<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\MenuSection;
use App\Models\MenuTab;
use App\Models\Product;
use App\Models\Restaurant;
use App\Support\CurrentRestaurant;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CleanupMenuHierarchyCommand extends Command
{
    protected $signature = 'menu:hierarchy-cleanup
                            {--restaurant=human : Restoran slug}
                            {--category=icecek : Kategori slug}
                            {--dry-run : Önizleme — silme yapmaz}';

    protected $description = 'Yinelenen sekmeleri birleştirir, boş test gruplarını siler';

    private bool $dryRun = false;

    /** @var array<int, string> */
    private array $junkSectionSlugs = ['soguk', 'asdasd', 'asd', 'asdas'];

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        if ($this->dryRun) {
            $this->warn('Dry-run modu — veritabanı değiştirilmeyecek.');
        }

        $restaurant = Restaurant::query()
            ->where('slug', $this->option('restaurant'))
            ->first();

        if (! $restaurant) {
            $this->error('Restoran bulunamadı.');

            return self::FAILURE;
        }

        return CurrentRestaurant::run($restaurant, function () use ($restaurant) {
            $category = Category::query()
                ->where('slug', $this->option('category'))
                ->where('restaurant_id', $restaurant->id)
                ->first();

            if (! $category) {
                $this->error('Kategori bulunamadı.');

                return self::FAILURE;
            }

            $this->info("Temizlik: {$restaurant->slug} / {$category->slug} (kategori #{$category->id})");

            $this->cleanupDuplicateTabs($category);
            $this->cleanupJunkSections($category);

            $this->newLine();
            $this->info('Tamamlandı.');

            return self::SUCCESS;
        });
    }

    private function cleanupDuplicateTabs(Category $category): void
    {
        $tabs = MenuTab::query()
            ->where('category_id', $category->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($tabs->isEmpty()) {
            $this->line('Sekme yok.');

            return;
        }

        /** @var Collection<string, Collection<int, MenuTab>> $groups */
        $groups = $tabs->groupBy(fn (MenuTab $tab) => $this->normalizedTabName($tab));

        foreach ($groups as $label => $group) {
            if ($group->count() <= 1) {
                continue;
            }

            $keeper = $group->sort(function (MenuTab $a, MenuTab $b) {
                $countCompare = $this->countTabProducts($b->id) <=> $this->countTabProducts($a->id);
                if ($countCompare !== 0) {
                    return $countCompare;
                }

                $orderCompare = $a->sort_order <=> $b->sort_order;
                if ($orderCompare !== 0) {
                    return $orderCompare;
                }

                return $a->id <=> $b->id;
            })->first();

            if (! $keeper instanceof MenuTab) {
                continue;
            }

            $this->warn("Yinelenen sekme «{$label}»: {$group->count()} adet → #{$keeper->id} korunacak");

            foreach ($group as $tab) {
                if ($tab->id === $keeper->id) {
                    continue;
                }

                $this->mergeTabInto($tab, $keeper);

                if ($this->countTabProducts($tab->id) > 0) {
                    $this->warn("  → #{$tab->id} silinmedi — hâlâ bağlı ürün var (gruplu sekme olabilir).");

                    continue;
                }

                $this->deleteTab($tab);
            }
        }
    }

    private function cleanupJunkSections(Category $category): void
    {
        $tabIds = MenuTab::query()
            ->where('category_id', $category->id)
            ->pluck('id');

        if ($tabIds->isEmpty()) {
            return;
        }

        $sections = MenuSection::query()
            ->whereIn('menu_tab_id', $tabIds)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($sections as $section) {
            if (! $this->isJunkSection($section)) {
                continue;
            }

            $productCount = $this->countSectionProducts($section->id);
            if ($productCount > 0) {
                $this->line("Atlandı (ürünlü): grup #{$section->id} «{$section->localizedName('tr')}»");

                continue;
            }

            $this->line("Siliniyor: grup #{$section->id} «{$section->localizedName('tr')}» (slug: {$section->slug})");

            if (! $this->dryRun) {
                $section->delete();
            }
        }
    }

    private function isJunkSection(MenuSection $section): bool
    {
        if (in_array($section->slug, $this->junkSectionSlugs, true)) {
            return true;
        }

        $name = $this->normalizedTabName($section);

        return in_array($name, ['soğuk', 'asd', 'asdasd', 'asdas'], true);
    }

    private function mergeTabInto(MenuTab $from, MenuTab $to): void
    {
        if (Schema::hasColumn('products', 'menu_tab_id')) {
            $moved = Product::query()->where('menu_tab_id', $from->id)->count();
            if ($moved > 0) {
                $this->line("  → {$moved} ürün #{$from->id} sekmesinden #{$to->id} sekmesine taşınıyor (menu_tab_id)");
                if (! $this->dryRun) {
                    Product::query()
                        ->where('menu_tab_id', $from->id)
                        ->update(['menu_tab_id' => $to->id]);
                }
            }
        }

        if (! Schema::hasTable('menu_sections') || ! Schema::hasColumn('products', 'menu_section_id')) {
            return;
        }

        foreach (MenuSection::query()->where('menu_tab_id', $from->id)->get() as $section) {
            $existing = MenuSection::query()
                ->where('menu_tab_id', $to->id)
                ->where('slug', $section->slug)
                ->first();

            if ($existing) {
                $moved = Product::query()->where('menu_section_id', $section->id)->count();
                if ($moved > 0) {
                    $this->line("  → {$moved} ürün «{$section->localizedName('tr')}» grubundan «{$existing->localizedName('tr')}» grubuna taşınıyor");
                    if (! $this->dryRun) {
                        Product::query()
                            ->where('menu_section_id', $section->id)
                            ->update(['menu_section_id' => $existing->id]);
                    }
                }

                $this->line("  → Yinelenen grup siliniyor: #{$section->id} «{$section->localizedName('tr')}»");
                if (! $this->dryRun) {
                    $section->delete();
                }

                continue;
            }

            $this->line("  → Grup taşınıyor: #{$section->id} «{$section->localizedName('tr')}» → sekme #{$to->id}");
            if (! $this->dryRun) {
                $section->update(['menu_tab_id' => $to->id]);
            }
        }
    }

    private function deleteTab(MenuTab $tab): void
    {
        $this->line("  → Sekme siliniyor: #{$tab->id} ({$tab->slug})");

        if ($this->dryRun) {
            return;
        }

        if (Schema::hasTable('menu_sections')) {
            MenuSection::query()->where('menu_tab_id', $tab->id)->delete();
        }

        $tab->delete();
    }

    private function normalizedTabName(MenuTab|MenuSection $model): string
    {
        $name = method_exists($model, 'localizedName')
            ? $model->localizedName('tr')
            : '';

        return Str::lower(trim($name));
    }

    private function countTabProducts(int $tabId): int
    {
        $total = 0;

        if (Schema::hasColumn('products', 'menu_tab_id')) {
            $total += (int) Product::query()->where('menu_tab_id', $tabId)->count();
        }

        if (Schema::hasTable('menu_sections') && Schema::hasColumn('products', 'menu_section_id')) {
            $sectionIds = MenuSection::query()->where('menu_tab_id', $tabId)->pluck('id');
            if ($sectionIds->isNotEmpty()) {
                $total += (int) Product::query()->whereIn('menu_section_id', $sectionIds)->count();
            }
        }

        return $total;
    }

    private function countSectionProducts(int $sectionId): int
    {
        if (! Schema::hasColumn('products', 'menu_section_id')) {
            return 0;
        }

        return (int) Product::query()->where('menu_section_id', $sectionId)->count();
    }
}
