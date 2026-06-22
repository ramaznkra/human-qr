<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\MenuSection;
use App\Models\MenuTab;
use App\Models\Product;
use App\Support\MenuCatalogBroadcaster;
use App\Support\MenuHierarchyCatalog;
use App\Support\CurrentRestaurant;
use App\Support\TenantRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class MenuHierarchyController extends Controller
{
    public function tabs(Category $category): JsonResponse
    {
        try {
            if (! $this->hierarchyTablesReady()) {
                return response()->json([
                    'tabs' => [],
                    'warning' => 'Menü hiyerarşisi tabloları eksik. Sunucuda migration dosyalarını çalıştırın.',
                ]);
            }

            $tenantError = $this->tenantCategoryError($category);
            if ($tenantError !== null) {
                return response()->json(['tabs' => [], 'message' => $tenantError], 403);
            }

            $tabs = MenuTab::query()
                ->where('category_id', $category->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->unique(fn (MenuTab $tab) => $tab->id)
                ->values();

            $category->setRelation('menuTabs', $tabs);
            $visibleTabIds = MenuHierarchyCatalog::visibleMenuTabIds($category);

            $tabs = $tabs
                ->map(fn (MenuTab $tab) => $this->formatTab($tab, $visibleTabIds->contains($tab->id)))
                ->all();

            return response()->json(['tabs' => $tabs]);
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Menu hierarchy tabs failed', [
                'category_id' => $category->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'tabs' => [],
                'warning' => 'Sekmeler yüklenemedi. Migration durumunu kontrol edin veya aşağıdan yeni sekme ekleyin.',
                'debug' => config('app.debug') ? $e->getMessage() : null,
            ]);
        }
    }

    public function sections(MenuTab $menuTab): JsonResponse
    {
        try {
            $tenantError = $this->tenantTabError($menuTab);
            if ($tenantError !== null) {
                return response()->json(['sections' => [], 'message' => $tenantError], 403);
            }

            if ($menuTab->isFlat()) {
                return response()->json(['sections' => [], 'layout' => MenuTab::LAYOUT_FLAT]);
            }

            if (! Schema::hasTable('menu_sections')) {
                return response()->json([
                    'sections' => [],
                    'layout' => MenuTab::LAYOUT_GROUPED,
                    'warning' => 'menu_sections tablosu henüz yok.',
                ]);
            }

            $sections = MenuSection::query()
                ->where('menu_tab_id', $menuTab->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (MenuSection $section) => [
                    'id' => $section->id,
                    'name' => $section->localizedName('tr'),
                    'slug' => $section->slug,
                    'products_count' => $this->countSectionProducts($section->id),
                ])
                ->values()
                ->all();

            return response()->json([
                'sections' => $sections,
                'layout' => MenuTab::LAYOUT_GROUPED,
            ]);
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Menu hierarchy sections failed', [
                'menu_tab_id' => $menuTab->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'sections' => [],
                'warning' => 'Gruplar yüklenemedi. Aşağıdan yeni grup ekleyebilirsiniz.',
                'debug' => config('app.debug') ? $e->getMessage() : null,
            ]);
        }
    }

    public function storeTab(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id' => ['required', TenantRules::existsModel(Category::class)],
            'name' => ['required', 'string', 'max:80'],
            'layout' => ['required', 'in:'.MenuTab::LAYOUT_GROUPED.','.MenuTab::LAYOUT_FLAT],
        ]);

        if (! $this->hierarchyTablesReady()) {
            return response()->json([
                'message' => 'Menü hiyerarşisi tabloları eksik. Önce migration çalıştırın.',
            ], 503);
        }

        $category = Category::query()->findOrFail($data['category_id']);
        $tenantError = $this->tenantCategoryError($category);
        if ($tenantError !== null) {
            return response()->json(['message' => $tenantError], 403);
        }

        $name = trim($data['name']);
        $normalizedName = Str::lower($name);

        $existing = MenuTab::query()
            ->where('category_id', $category->id)
            ->get()
            ->first(fn (MenuTab $tab) => Str::lower(trim($tab->localizedName('tr'))) === $normalizedName);

        if ($existing) {
            return response()->json([
                'tab' => $this->formatTab($existing),
                'existing' => true,
            ]);
        }

        $slug = Str::slug($name);
        if ($slug === '') {
            $slug = 'sekme-'.time();
        }

        $baseSlug = $slug;
        $i = 1;
        while (
            MenuTab::query()
                ->where('restaurant_id', $category->restaurant_id)
                ->where('category_id', $category->id)
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $baseSlug.'-'.$i++;
        }

        $payload = [
            'restaurant_id' => $category->restaurant_id,
            'category_id' => $category->id,
            'name' => ['tr' => $name, 'en' => $name, 'ru' => $name],
            'slug' => $slug,
            'sort_order' => (int) MenuTab::query()->where('category_id', $category->id)->max('sort_order') + 1,
            'is_active' => true,
        ];

        if (Schema::hasColumn('menu_tabs', 'layout')) {
            $payload['layout'] = $data['layout'];
        }

        $tab = MenuTab::create($payload);

        MenuCatalogBroadcaster::notify($category->restaurant_id);

        return response()->json([
            'tab' => $this->formatTab($tab),
        ], 201);
    }

    public function storeSection(Request $request): JsonResponse
    {
        $data = $request->validate([
            'menu_tab_id' => ['required', 'exists:menu_tabs,id'],
            'name' => ['required', 'string', 'max:80'],
        ]);

        $tab = MenuTab::query()->findOrFail($data['menu_tab_id']);
        $tenantError = $this->tenantTabError($tab);
        if ($tenantError !== null) {
            return response()->json(['message' => $tenantError], 403);
        }

        if ($tab->isFlat()) {
            return response()->json([
                'message' => 'Düz liste sekmelerine grup eklenemez.',
            ], 422);
        }

        if (! Schema::hasTable('menu_sections')) {
            return response()->json([
                'message' => 'menu_sections tablosu henüz yok.',
            ], 503);
        }

        $name = trim($data['name']);
        $normalizedName = Str::lower($name);

        $existing = MenuSection::query()
            ->where('menu_tab_id', $tab->id)
            ->get()
            ->first(fn (MenuSection $section) => Str::lower(trim($section->localizedName('tr'))) === $normalizedName);

        if ($existing) {
            return response()->json([
                'section' => [
                    'id' => $existing->id,
                    'name' => $existing->localizedName('tr'),
                    'slug' => $existing->slug,
                    'products_count' => $this->countSectionProducts($existing->id),
                ],
                'existing' => true,
            ]);
        }

        $slug = Str::slug($name);
        if ($slug === '') {
            $slug = 'grup-'.time();
        }

        $baseSlug = $slug;
        $i = 1;
        while (
            MenuSection::query()
                ->where('restaurant_id', $tab->restaurant_id)
                ->where('menu_tab_id', $tab->id)
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $baseSlug.'-'.$i++;
        }

        $section = MenuSection::create([
            'restaurant_id' => $tab->restaurant_id,
            'menu_tab_id' => $tab->id,
            'name' => ['tr' => $name, 'en' => $name, 'ru' => $name],
            'slug' => $slug,
            'sort_order' => (int) MenuSection::query()->where('menu_tab_id', $tab->id)->max('sort_order') + 1,
            'is_active' => true,
        ]);

        MenuCatalogBroadcaster::notify($tab->restaurant_id);

        return response()->json([
            'section' => [
                'id' => $section->id,
                'name' => $section->localizedName('tr'),
                'slug' => $section->slug,
                'products_count' => 0,
            ],
        ], 201);
    }

    public function destroyTabById(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tab_id' => ['required', 'integer'],
            'force' => ['sometimes', 'boolean'],
        ]);

        $menuTab = MenuTab::query()->find($data['tab_id']);
        if (! $menuTab) {
            return response()->json([
                'message' => "Sekme bulunamadı (#{$data['tab_id']}). Sayfayı yenileyip tekrar deneyin.",
            ], 404);
        }

        return $this->destroyTab($request, $menuTab);
    }

    public function destroySectionById(Request $request): JsonResponse
    {
        $data = $request->validate([
            'section_id' => ['required', 'integer'],
            'force' => ['sometimes', 'boolean'],
        ]);

        $menuSection = MenuSection::query()->find($data['section_id']);
        if (! $menuSection) {
            return response()->json([
                'message' => "Grup bulunamadı (#{$data['section_id']}). Sayfayı yenileyip tekrar deneyin.",
            ], 404);
        }

        return $this->destroySection($request, $menuSection);
    }

    public function destroyTab(Request $request, MenuTab $menuTab): JsonResponse
    {
        $tenantError = $this->tenantTabError($menuTab);
        if ($tenantError !== null) {
            return response()->json(['message' => $tenantError], 403);
        }

        $blocking = MenuHierarchyCatalog::blockingProductsForTab($menuTab->id);
        $force = $request->boolean('force');

        if ($blocking->isNotEmpty() && ! $force) {
            return response()->json([
                'message' => 'Bu sekmede hâlâ ürün var. Onaylarsanız ürünlerle birlikte silinebilir.',
                'blocking_products' => $blocking
                    ->map(fn (Product $product) => [
                        'id' => $product->id,
                        'name' => $product->localizedName('tr'),
                        'is_available' => (bool) $product->is_available,
                    ])
                    ->values()
                    ->all(),
            ], 422);
        }

        $deletedProducts = 0;
        if ($blocking->isNotEmpty()) {
            $deletedProducts = $blocking->count();
            Product::query()->whereIn('id', $blocking->pluck('id'))->delete();
        }

        if (Schema::hasTable('menu_sections')) {
            MenuSection::query()
                ->where('menu_tab_id', $menuTab->id)
                ->delete();
        }

        $menuTab->delete();

        MenuCatalogBroadcaster::notify($menuTab->restaurant_id);

        return response()->json([
            'deleted' => true,
            'deleted_products' => $deletedProducts,
        ]);
    }

    public function destroySection(Request $request, MenuSection $menuSection): JsonResponse
    {
        $tenantError = $this->tenantSectionError($menuSection);
        if ($tenantError !== null) {
            return response()->json(['message' => $tenantError], 403);
        }

        $productCount = $this->countSectionProducts($menuSection->id);
        $force = $request->boolean('force');

        if ($productCount > 0 && ! $force) {
            return response()->json([
                'message' => 'Bu grupta hâlâ ürün var. Onaylarsanız ürünlerle birlikte silinebilir.',
                'products_count' => $productCount,
            ], 422);
        }

        $deletedProducts = 0;
        if ($productCount > 0) {
            $deletedProducts = $productCount;
            Product::query()
                ->where('menu_section_id', $menuSection->id)
                ->delete();
        }

        $menuSection->delete();

        MenuCatalogBroadcaster::notify($menuSection->restaurant_id);

        return response()->json([
            'deleted' => true,
            'deleted_products' => $deletedProducts,
        ]);
    }

    private function hierarchyTablesReady(): bool
    {
        return Schema::hasTable('menu_tabs');
    }

    private function tenantCategoryError(Category $category): ?string
    {
        $restaurantId = CurrentRestaurant::resolveId();
        if ($restaurantId === null) {
            return 'Restoran oturumu bulunamadı. Tekrar giriş yapın.';
        }

        if ((int) $category->restaurant_id !== (int) $restaurantId) {
            return 'Bu kategoriye erişim yok.';
        }

        return null;
    }

    private function tenantTabError(MenuTab $menuTab): ?string
    {
        $restaurantId = CurrentRestaurant::resolveId();
        if ($restaurantId === null) {
            return 'Restoran oturumu bulunamadı. Tekrar giriş yapın.';
        }

        if ((int) $menuTab->restaurant_id !== (int) $restaurantId) {
            return 'Bu sekmeye erişim yok.';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatTab(MenuTab $tab, bool $visibleOnMenu = true): array
    {
        $blocking = MenuHierarchyCatalog::blockingProductsForTab($tab->id);

        return [
            'id' => $tab->id,
            'name' => $tab->localizedName('tr'),
            'slug' => $tab->slug,
            'layout' => Schema::hasColumn('menu_tabs', 'layout')
                ? ($tab->layout ?? MenuTab::LAYOUT_GROUPED)
                : MenuTab::LAYOUT_GROUPED,
            'products_count' => $blocking->count(),
            'sections_count' => $this->countTabSections($tab->id),
            'can_delete' => $blocking->isEmpty(),
            'visible_on_menu' => $visibleOnMenu,
            'blocking_products' => $blocking
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->localizedName('tr'),
                    'is_available' => (bool) $product->is_available,
                ])
                ->values()
                ->all(),
        ];
    }

    private function tenantSectionError(MenuSection $menuSection): ?string
    {
        $restaurantId = CurrentRestaurant::resolveId();
        if ($restaurantId === null) {
            return 'Restoran oturumu bulunamadı. Tekrar giriş yapın.';
        }

        if ((int) $menuSection->restaurant_id !== (int) $restaurantId) {
            return 'Bu gruba erişim yok.';
        }

        return null;
    }

    private function countTabSections(int $tabId): int
    {
        if (! Schema::hasTable('menu_sections')) {
            return 0;
        }

        try {
            return (int) DB::table('menu_sections')
                ->where('menu_tab_id', $tabId)
                ->where('is_active', true)
                ->count();
        } catch (Throwable $e) {
            Log::warning('countTabSections failed', ['tab_id' => $tabId, 'message' => $e->getMessage()]);

            return 0;
        }
    }

    private function countTabProducts(int $tabId): int
    {
        return MenuHierarchyCatalog::countTabProducts($tabId);
    }

    private function countSectionProducts(int $sectionId): int
    {
        return MenuHierarchyCatalog::countSectionProducts($sectionId);
    }

    private function tabHasProducts(MenuTab $menuTab): bool
    {
        return $this->countTabProducts($menuTab->id) > 0;
    }
}
