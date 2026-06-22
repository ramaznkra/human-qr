<?php

namespace App\Models\Scopes;

use App\Support\CurrentRestaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * SaaS tenant izolasyonu — tüm sorgulara otomatik restaurant_id filtresi.
 *
 * Bağlam kaynakları (CurrentRestaurant::resolveId):
 * - Personel oturumu: session admin_restaurant_id / auth kullanıcısı
 * - Müşteri QR menü: ResolveRestaurantFromTable middleware
 * - Mutfak kiosk: session kiosk_restaurant_id
 *
 * Bağlam yoksa fail-safe: hiçbir satır dönmez (veri sızıntısı engeli).
 * Route model binding + global scope: başka restoranın ID'si → 404.
 */
class RestaurantScope implements Scope
{
    public const NAME = 'restaurant';

    public function apply(Builder $builder, Model $model): void
    {
        $restaurantId = CurrentRestaurant::resolveId();

        if ($restaurantId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where(
            $model->qualifyColumn('restaurant_id'),
            $restaurantId,
        );
    }
}
