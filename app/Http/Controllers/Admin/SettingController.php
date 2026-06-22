<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\CurrentRestaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(): View
    {
        $settings = Setting::allCached();
        $restaurant = CurrentRestaurant::get();

        return view('admin.settings.edit', compact('settings', 'restaurant'));
    }

    public function update(Request $request): RedirectResponse
    {
        $keys = [
            'venue_name', 'venue_slogan', 'brand_mark', 'venue_tagline', 'venue_phone', 'venue_address',
            'currency', 'order_enabled', 'display_interval',
            'daily_motto', 'wifi_password', 'show_motto_banner', 'show_wifi_banner',
            'spotify_url', 'spotify_title', 'instagram_url', 'instagram_handle',
        ];

        foreach ($keys as $key) {
            if ($request->has($key)) {
                Setting::set($key, $request->input($key));
            }
        }

        Setting::clearCache();

        return back()->with('success', 'Ayarlar kaydedildi.');
    }
}
