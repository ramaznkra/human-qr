<?php

namespace App\Http\Controllers\Waiter;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\SiteBranding;
use Illuminate\Http\JsonResponse;

class WaiterPwaController extends Controller
{
    public function manifest(): JsonResponse
    {
        $settings = Setting::allCached();
        $venue = $settings['venue_name'];

        return response()->json([
            'name' => "{$venue} — Garson",
            'short_name' => $venue,
            'description' => "{$venue} garson paneli",
            'start_url' => '/waiter/dashboard',
            'scope' => '/',
            'id' => '/waiter/dashboard',
            'display' => 'standalone',
            'background_color' => '#F4EFE8',
            'theme_color' => '#121110',
            'orientation' => 'portrait',
            'icons' => SiteBranding::manifestIcons(),
        ], 200, [
            'Content-Type' => 'application/manifest+json; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
