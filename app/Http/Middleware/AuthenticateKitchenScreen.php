<?php

namespace App\Http\Middleware;

use App\Models\Restaurant;
use App\Support\CurrentRestaurant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mutfak / kasa kiosk ekranları için restoran token doğrulaması.
 */
class AuthenticateKitchenScreen
{
    public function handle(Request $request, Closure $next): Response
    {
        if (session('admin_logged_in') && session('admin_restaurant_id')) {
            $restaurant = Restaurant::query()->find(session('admin_restaurant_id'));
            if ($restaurant?->is_active) {
                CurrentRestaurant::set($restaurant);

                return $next($request);
            }
        }

        $token = $request->header('X-Kitchen-Token')
            ?? $request->query('kiosk')
            ?? $request->query('kitchen_token')
            ?? session('kiosk_kitchen_token');

        if (! $token) {
            return $this->deny($request, 'Kiosk erişim token\'ı gerekli.');
        }

        $restaurant = Restaurant::query()
            ->where('kitchen_token', $token)
            ->where('is_active', true)
            ->first();

        if (! $restaurant) {
            return $this->deny($request, 'Geçersiz kiosk token.');
        }

        session([
            'kiosk_restaurant_id' => $restaurant->id,
            'kiosk_kitchen_token' => $token,
        ]);

        CurrentRestaurant::set($restaurant);

        return $next($request);
    }

    private function deny(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 401);
        }

        abort(401, $message);
    }
}
