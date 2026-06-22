<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CafeGallery;
use App\Services\MenuImageOptimizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CafeGalleryController extends Controller
{
    public function __construct(
        private readonly MenuImageOptimizer $images,
    ) {}
    public function index(): View
    {
        $galleries = CafeGallery::orderBy('sort_order')->get();

        return view('admin.cafe-galleries.index', compact('galleries'));
    }

    public function create(): View
    {
        return view('admin.cafe-galleries.form', ['gallery' => new CafeGallery]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['image' => 'required|image|max:5120']);
        $data = $this->validated($request);
        $data['image_path'] = $this->images->storeGallery($request->file('image'));
        CafeGallery::create($data);

        return redirect()->route('admin.cafe-galleries.index')->with('success', 'Social Spotted kartı eklendi.');
    }

    public function edit(CafeGallery $cafe_gallery): View
    {
        return view('admin.cafe-galleries.form', ['gallery' => $cafe_gallery]);
    }

    public function update(Request $request, CafeGallery $cafe_gallery): RedirectResponse
    {
        $data = $this->validated($request);
        if ($request->hasFile('image')) {
            $data['image_path'] = $this->images->storeGallery($request->file('image'));
        } else {
            unset($data['image_path']);
        }
        $cafe_gallery->update($data);

        return redirect()->route('admin.cafe-galleries.index')->with('success', 'Social Spotted kartı güncellendi.');
    }

    public function destroy(CafeGallery $cafe_gallery): RedirectResponse
    {
        $cafe_gallery->delete();

        return redirect()->route('admin.cafe-galleries.index')->with('success', 'Social Spotted kartı silindi.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:150',
            'description' => 'nullable|string|max:500',
            'badge_text' => 'nullable|string|max:80',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'image' => 'nullable|image|max:5120',
        ]);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['badge_text'] = $data['badge_text'] ?? 'Spotted at HSP ✨';

        return $data;
    }
}
