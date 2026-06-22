<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\MenuImageOptimizer;
use App\Support\MenuTranslations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(
        private readonly MenuImageOptimizer $images,
    ) {}

    public function index(): View
    {
        $categories = Category::withCount('products')->orderBy('sort_order')->get();

        $totalRevenueToday = (float) OrderItem::query()
            ->whereNotNull('product_id')
            ->whereHas('order', fn ($q) => $q
                ->whereDate('created_at', today())
                ->where('status', Order::STATUS_DELIVERED))
            ->selectRaw('COALESCE(SUM(quantity * unit_price), 0) as total')
            ->value('total');

        $revenueByCategory = OrderItem::query()
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->whereHas('order', fn ($q) => $q
                ->whereDate('created_at', today())
                ->where('status', Order::STATUS_DELIVERED))
            ->groupBy('products.category_id')
            ->selectRaw('products.category_id, COALESCE(SUM(order_items.quantity * order_items.unit_price), 0) as revenue')
            ->pluck('revenue', 'category_id');

        return view('admin.categories.index', compact('categories', 'totalRevenueToday', 'revenueByCategory'));
    }

    public function create(): View
    {
        return view('admin.categories.form', ['category' => new Category]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data = $this->resolveImage($request, $data);
        Category::create($data);

        return redirect()->route('admin.categories.index')->with('success', 'Kategori eklendi.');
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.form', compact('category'));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $this->validated($request, $category);
        $data = $this->resolveImage($request, $data, $category);
        $category->update($data);

        return redirect()->route('admin.categories.index')->with('success', 'Kategori güncellendi.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return redirect()->route('admin.categories.index')->with('success', 'Kategori silindi.');
    }

    public function toggleActive(Category $category): \Illuminate\Http\JsonResponse
    {
        $category->update(['is_active' => ! $category->is_active]);

        return response()->json([
            'success' => true,
            'category_id' => $category->id,
            'is_active' => $category->is_active,
            'label' => $category->is_active ? 'Aktif' : 'Pasif',
        ]);
    }

    /** Kategori sıralamasını toplu günceller (drag-and-drop için hazır). */
    public function updateSortOrder(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'categories' => 'required|array|min:1',
            'categories.*.id' => ['required', 'integer', 'exists:categories,id'],
            'categories.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['categories'] as $row) {
            Category::query()
                ->whereKey($row['id'])
                ->update(['sort_order' => (int) $row['sort_order']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Kategori sırası güncellendi.',
        ]);
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $translations = MenuTranslations::validated($request, maxName: 100);

        $data = $request->validate([
            'slug' => 'nullable|string|max:100',
            'type' => 'required|in:'.implode(',', Category::stationTypes()),
            'icon' => 'nullable|string|max:50',
            'image' => 'nullable|image|max:3072',
            'remove_image' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $data = array_merge($data, $translations);
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']['tr'] ?? $data['name']['en'] ?? '');
        $data['is_active'] = filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN);

        return $data;
    }

    private function resolveImage(Request $request, array $data, ?Category $category = null): array
    {
        $removeImage = $request->boolean('remove_image');
        unset($data['image'], $data['remove_image']);

        $currentImage = $category?->image;
        if ($request->hasFile('image')) {
            if ($this->isStoredCategoryImage($currentImage)) {
                Storage::disk('public')->delete($currentImage);
            }
            $data['image'] = $this->images->storeCategory($request->file('image'));
            return $data;
        }

        if ($removeImage) {
            if ($this->isStoredCategoryImage($currentImage)) {
                Storage::disk('public')->delete($currentImage);
            }
            $data['image'] = null;
            return $data;
        }

        return $data;
    }

    private function isStoredCategoryImage(?string $path): bool
    {
        return filled($path) && str_starts_with($path, 'categories/');
    }
}
