<?php

namespace App\Http\Middleware;

use App\Models\Restaurant;
use App\Models\User;
use App\Support\CurrentRestaurant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin / garson oturumundan restoran bağlamını yükler.
 */
class SetStaffRestaurantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! session('admin_logged_in')) {
            return $next($request);
        }

        $restaurantId = session('admin_restaurant_id');

        // Eski oturumlar (migration öncesi): kullanıcıdan restoranı tamamla.
        if (! $restaurantId && session('admin_user_id')) {
            $user = User::query()->find(session('admin_user_id'));
            if ($user?->restaurant_id) {
                $restaurantId = $user->restaurant_id;
                session(['admin_restaurant_id' => $restaurantId]);
            }
        }

        // Tek restoran kurulumu: otomatik fallback.
        if (! $restaurantId && Restaurant::query()->where('is_active', true)->count() === 1) {
            $restaurantId = Restaurant::query()->where('is_active', true)->value('id');
            session(['admin_restaurant_id' => $restaurantId]);
        }

        if (! $restaurantId) {
            session()->forget(['admin_logged_in', 'admin_user_id', 'admin_name', 'admin_role', 'admin_restaurant_id']);

            if ($request->expectsJson() || $request->is('admin/api/*', 'api/waiter/*', 'api/admin/*')) {
                return response()->json(['message' => 'Oturum süresi doldu. Lütfen tekrar giriş yapın.'], 401);
            }

            return redirect()->route('admin.login')
                ->with('error', 'Oturum süresi doldu. Lütfen tekrar giriş yapın.');
        }

        $restaurant = Restaurant::query()->find($restaurantId);
        if (! $restaurant || ! $restaurant->is_active) {
            session()->forget(['admin_logged_in', 'admin_user_id', 'admin_name', 'admin_role', 'admin_restaurant_id']);

            if ($request->expectsJson() || $request->is('admin/api/*', 'api/waiter/*', 'api/admin/*')) {
                return response()->json(['message' => 'Restoran erişimi geçersiz. Tekrar giriş yapın.'], 403);
            }

            return redirect()->route('admin.login')
                ->with('error', 'Restoran erişimi geçersiz. Tekrar giriş yapın.');
        }

        CurrentRestaurant::set($restaurant);

        return $next($request);
    }
}
