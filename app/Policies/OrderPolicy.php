<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy extends TenantPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $this->belongsToCurrentRestaurant($order);
    }

    public function update(User $user, Order $order): bool
    {
        return $this->view($user, $order);
    }

    public function delete(User $user, Order $order): bool
    {
        return $this->view($user, $order);
    }
}
