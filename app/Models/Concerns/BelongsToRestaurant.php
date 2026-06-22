<?php

namespace App\Models\Concerns;

use App\Models\Restaurant;
use App\Models\Scopes\RestaurantScope;
use App\Support\CurrentRestaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToRestaurant
{
    public static function bootBelongsToRestaurant(): void
    {
        static::addGlobalScope(RestaurantScope::NAME, new RestaurantScope);

        static::creating(function (Model $model) {
            if (! empty($model->restaurant_id)) {
                return;
            }

            $restaurantId = CurrentRestaurant::resolveId();
            if ($restaurantId !== null) {
                $model->restaurant_id = $restaurantId;
            }
        });
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Route model binding: global scope sayesinde başka restoranın kaydı 404 döner.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?: $this->getRouteKeyName();

        return static::query()
            ->where($field, $value)
            ->firstOrFail();
    }

    public function scopeForRestaurant(Builder $query, int $restaurantId): Builder
    {
        return $query->withoutGlobalScope(RestaurantScope::NAME)
            ->where($query->getModel()->qualifyColumn('restaurant_id'), $restaurantId);
    }
}
