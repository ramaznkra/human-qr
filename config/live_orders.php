<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tamamlanan sipariş saklama süresi (dakika)
    |--------------------------------------------------------------------------
    |
    | Canlı panelde "Tamamlanan" sekmesinde görünen kapalı adisyonlar bu süre
    | sonunda otomatik olarak canlı listeden gizlenir (admin arşivinde kalır). 0 = kapalı.
    |
    */
    'completed_retention_minutes' => (int) env('LIVE_ORDERS_COMPLETED_RETENTION_MINUTES', 120),

];
