<?php

namespace App\Policies;

use App\Support\CurrentRestaurant;
use Illuminate\Database\Eloquent\Model;

abstract class TenantPolicy
{
    protected function belongsToCurrentRestaurant(?Model $model): bool
    {
        if ($model === null) {
            return false;
        }

        if (! isset($model->restaurant_id)) {
            return false;
        }

        return (int) $model->restaurant_id === (int) CurrentRestaurant::resolveId();
    }
}
