<?php

namespace App\Support;

class MenuCatalogBroadcaster
{
    public static function notify(?int $restaurantId = null): void
    {
        // Canlı menü yenilemesi kapalı — istemci dinlemiyor.
    }
}
