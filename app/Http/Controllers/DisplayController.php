<?php

namespace App\Http\Controllers;

use App\Models\DisplaySlide;
use App\Models\Setting;
use Illuminate\View\View;

class DisplayController extends Controller
{
    public function index(): View
    {
        $slides = DisplaySlide::active()->get();
        $settings = Setting::allCached();

        return view('display.index', compact('slides', 'settings'));
    }

    public function api(): \Illuminate\Http\JsonResponse
    {
        $slides = DisplaySlide::active()->get()->map(fn ($s) => [
            'id' => $s->id,
            'badge' => $s->badge,
            'title' => $s->title,
            'subtitle' => $s->subtitle,
            'image' => $s->image_url,
            'duration' => $s->duration,
        ]);

        return response()->json(['slides' => $slides]);
    }
}
