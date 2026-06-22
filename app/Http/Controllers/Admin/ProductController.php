<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOptionGroup;
use App\Services\MenuImageOptimizer;
use App\Services\ProductOptionSyncService;
use App\Support\MenuCatalogBroadcaster;
use App\Support\MenuHierarchyCatalog;
use App\Support\MenuTranslations;
use App\Support\TenantRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        private readonly MenuImageOptimizer $images,
        private readonly ProductOptionSyncService $optionSync,
    ) {}

    public function index(Request $request): View
    {
        $products = Product::with(['category', 'optionGroups.options'])
            ->orderBy('sort_order')
            ->get();
        $categories = Category::active()->orderBy('sort_order')->get();

        return view('admin.products.index', compact('products', 'categories'));
    }

    public function create(): View
    {
        $categories = Category::active()->orderBy('sort_order')->get();

        return view('admin.products.form', [
            'product' => new Product,
            'categories' => $categories,
            'badgeSuggestions' => $this->badgeSuggestions(),
            'menuHierarchyCatalog' => MenuHierarchyCatalog::forCategories($categories),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->guardPostPayload($request)) {
            return $redirect;
        }

        $data = $this->validated($request);
        if ($request->hasFile('image')) {
            $data['image'] = $this->images->storeProduct($request->file('image'));
        }
        $data['is_available'] = true;
        $data['in_stock'] = true;

        $product = DB::transaction(function () use ($request, $data) {
            $product = Product::create($data);
            $this->optionSync->sync($product, $request->input('option_groups'));

            return $product;
        });

        MenuCatalogBroadcaster::notify($product->restaurant_id);

        return redirect()->route('admin.products.index')->with('success', 'Ürün eklendi.');
    }

    public function editPanel(Product $product): RedirectResponse
    {
        return redirect()->route('admin.products.edit', $product);
    }

    public function edit(Product $product): View
    {
        $product->load(['optionGroups.options']);
        $categories = Category::active()->orderBy('sort_order')->get();

        return view('admin.products.form', [
            'product' => $product,
            'categories' => $categories,
            'badgeSuggestions' => $this->badgeSuggestions(),
            'menuHierarchyCatalog' => MenuHierarchyCatalog::forCategories($categories),
        ]);
    }

    /**
     * Hazır rozetler + daha önce kullanılmış rozetler (tekrarsız).
     *
     * @return array<int, string>
     */
    private function badgeSuggestions(): array
    {
        $defaults = ['Popüler', 'Yeni', 'Paket', 'Şefin Önerisi', 'İndirim', 'Acı', 'Vegan', 'Glutensiz'];

        $used = Product::query()
            ->whereNotNull('badge')
            ->where('badge', '!=', '')
            ->distinct()
            ->pluck('badge')
            ->all();

        return collect($defaults)
            ->merge($used)
            ->map(fn ($b) => trim((string) $b))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function update(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        if ($redirect = $this->guardPostPayload($request)) {
            return $redirect;
        }

        $data = $this->validated($request);
        if ($request->hasFile('image')) {
            $data['image'] = $this->images->storeProduct($request->file('image'));
        }

        DB::transaction(function () use ($request, $product, $data) {
            $product->update($data);
            $this->optionSync->sync($product, $request->input('option_groups'));
        });

        MenuCatalogBroadcaster::notify($product->restaurant_id);

        if ($request->header('X-Admin-Drawer')) {
            return response()->json(['success' => true, 'message' => 'Ürün güncellendi.']);
        }

        return redirect()->route('admin.products.index')->with('success', 'Ürün güncellendi.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $restaurantId = $product->restaurant_id;
        $product->delete();

        MenuCatalogBroadcaster::notify($restaurantId);

        return redirect()->route('admin.products.index')->with('success', 'Ürün silindi.');
    }

    public function toggleAvailability(Product $product): JsonResponse
    {
        $product->update(['is_available' => ! $product->is_available]);

        MenuCatalogBroadcaster::notify($product->restaurant_id);

        return response()->json([
            'success' => true,
            'product_id' => $product->id,
            'is_available' => $product->is_available,
            'label' => $product->is_available ? 'Menüde' : 'Gizli',
        ]);
    }

    public function toggleInStock(Product $product): JsonResponse
    {
        $product->update(['in_stock' => ! $product->in_stock]);

        MenuCatalogBroadcaster::notify($product->restaurant_id);

        return response()->json([
            'success' => true,
            'product_id' => $product->id,
            'in_stock' => $product->in_stock,
            'label' => $product->in_stock ? 'Stokta' : 'Tükendi',
        ]);
    }

    public function quickUpdate(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:120',
            'price' => 'nullable|numeric|min:0',
        ]);

        if (array_key_exists('name', $data) && $data['name'] !== null) {
            $product->setTranslation('name', 'tr', trim($data['name']));
        }

        if (array_key_exists('price', $data) && $data['price'] !== null) {
            $product->price = $data['price'];
        }

        $product->save();

        MenuCatalogBroadcaster::notify($product->restaurant_id);

        return response()->json([
            'success' => true,
            'product_id' => $product->id,
            'name' => $product->getTranslation('name', 'tr'),
            'price' => (float) $product->price,
            'price_formatted' => number_format((float) $product->price, 2, ',', '.').' ₺',
        ]);
    }

    private function validated(Request $request): array
    {
        $translations = MenuTranslations::validated($request);

        $data = $request->validate([
            'category_id' => ['required', TenantRules::existsModel(Category::class)],
            'type' => 'required|in:'.implode(',', Category::stationTypes()),
            'station' => 'nullable|in:'.implode(',', Product::stationOptions()),
            'price' => 'required|numeric|min:0',
            'badge' => 'nullable|string|max:30',
            'sort_order' => 'nullable|integer|min:0',
            'in_stock' => 'nullable|boolean',
            'image' => 'nullable|image|max:'.config('upload.product_image_max_kb', 10240),
            'menu_section_id' => ['nullable', 'exists:menu_sections,id'],
            'menu_tab_id' => ['nullable', 'exists:menu_tabs,id'],
            'option_groups' => 'nullable|array',
            'option_groups.*.id' => 'nullable|integer',
            'option_groups.*.name' => 'nullable|array',
            'option_groups.*.name.tr' => 'nullable|string|max:80',
            'option_groups.*.name.en' => 'nullable|string|max:80',
            'option_groups.*.name.ru' => 'nullable|string|max:80',
            'option_groups.*.type' => 'nullable|in:'.ProductOptionGroup::TYPE_SINGLE.','.ProductOptionGroup::TYPE_MULTIPLE,
            'option_groups.*.required' => 'nullable|boolean',
            'option_groups.*.sort_order' => 'nullable|integer|min:0',
            'option_groups.*.options' => 'nullable|array',
            'option_groups.*.options.*.id' => 'nullable|integer',
            'option_groups.*.options.*.name' => 'nullable|array',
            'option_groups.*.options.*.name.tr' => 'nullable|string|max:80',
            'option_groups.*.options.*.name.en' => 'nullable|string|max:80',
            'option_groups.*.options.*.name.ru' => 'nullable|string|max:80',
            'option_groups.*.options.*.price_modifier' => 'nullable|numeric|min:0',
            'option_groups.*.options.*.is_active' => 'nullable|boolean',
            'option_groups.*.options.*.is_default' => 'nullable|boolean',
            'option_groups.*.options.*.sort_order' => 'nullable|integer|min:0',
        ]);

        unset($data['option_groups']);

        if ($request->has('in_stock')) {
            $data['in_stock'] = $request->boolean('in_stock');
        }

        $data['station'] = Product::normalizeStation($data['station'] ?? null);

        if (empty($data['menu_section_id'])) {
            $data['menu_section_id'] = null;
        }

        if (empty($data['menu_tab_id'])) {
            $data['menu_tab_id'] = null;
        }

        if (! empty($data['menu_section_id'])) {
            $data['menu_tab_id'] = null;
        } elseif (! empty($data['menu_tab_id'])) {
            $data['menu_section_id'] = null;
        }

        return array_merge($data, $translations);
    }

    /**
     * PHP post_max_size aşıldığında $_POST tamamen boşalır; kullanıcıya net mesaj ver.
     */
    private function guardPostPayload(Request $request): ?RedirectResponse
    {
        if ($request->has('name')) {
            return null;
        }

        $contentLength = (int) $request->server('CONTENT_LENGTH', 0);
        if ($contentLength > 1000) {
            $maxMb = (int) ceil(config('upload.product_image_max_kb', 10240) / 1024);

            return redirect()->back()
                ->withInput($request->except(['image']))
                ->with('error', "Form verileri sunucuya ulaşmadı. Görsel {$maxMb} MB sınırını aşıyor olabilir; daha küçük bir görsel seçin veya görsel olmadan kaydedin.");
        }

        return null;
    }
}
