<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DisplaySlide;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DisplaySlideController extends Controller
{
    public function index(): View
    {
        $slides = DisplaySlide::orderBy('sort_order')->get();

        return view('admin.slides.index', compact('slides'));
    }

    public function create(): View
    {
        return view('admin.slides.form', ['slide' => new DisplaySlide]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['image' => 'required|image|max:5120']);
        $data = $this->validated($request);
        $data['image'] = $request->file('image')->store('slides', 'public');
        DisplaySlide::create($data);

        return redirect()->route('admin.slides.index')->with('success', 'Slayt eklendi.');
    }

    public function edit(DisplaySlide $slide): View
    {
        return view('admin.slides.form', compact('slide'));
    }

    public function update(Request $request, DisplaySlide $slide): RedirectResponse
    {
        $data = $this->validated($request);
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('slides', 'public');
        } else {
            unset($data['image']);
        }
        $slide->update($data);

        return redirect()->route('admin.slides.index')->with('success', 'Slayt güncellendi.');
    }

    public function destroy(DisplaySlide $slide): RedirectResponse
    {
        $slide->delete();

        return redirect()->route('admin.slides.index')->with('success', 'Slayt silindi.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:150',
            'subtitle' => 'nullable|string|max:255',
            'badge' => 'nullable|string|max:80',
            'duration' => 'required|integer|min:3|max:60',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'image' => 'nullable|image|max:5120',
        ]);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
