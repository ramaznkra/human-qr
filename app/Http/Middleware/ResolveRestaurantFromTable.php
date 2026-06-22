<?php

namespace App\Http\Middleware;

use App\Models\Restaurant;
use App\Models\Table;
use App\Support\CurrentRestaurant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Müşteri menüsü / sipariş isteklerinde restoranı masa UUID'sinden çözer.
 */
class ResolveRestaurantFromTable
{
    public function handle(Request $request, Closure $next): Response
    {
        if (CurrentRestaurant::has()) {
            return $next($request);
        }

        $token = $request->route('uuid')
            ?? $request->route('token')
            ?? $request->input('table_token')
            ?? $request->query('table_token');

        if ($token) {
            $table = Table::withoutGlobalScopes()
                ->where('is_active', true)
                ->where(fn ($q) => $q->where('uuid', $token)->orWhere('qr_token', $token))
                ->first();

            if ($table) {
                $restaurant = Restaurant::query()->find($table->restaurant_id);
                if ($restaurant?->is_active) {
                    CurrentRestaurant::set($restaurant);

                    return $next($request);
                }
            }
        }

        $slug = $request->route('restaurant');
        if ($slug) {
            $restaurant = Restaurant::query()
                ->where('slug', $slug)
                ->where('is_active', true)
                ->first();

            if ($restaurant) {
                CurrentRestaurant::set($restaurant);

                return $next($request);
            }
        }

        if (Restaurant::query()->where('is_active', true)->count() === 1) {
            $restaurant = Restaurant::query()->where('is_active', true)->first();
            if ($restaurant) {
                CurrentRestaurant::set($restaurant);

                return $next($request);
            }
        }

        abort(404, 'Restoran bulunamadı.');
    }
}
