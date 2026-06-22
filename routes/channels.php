<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('orders.{restaurantId}', fn () => true);
Broadcast::channel('menu.{restaurantId}', fn () => true);
