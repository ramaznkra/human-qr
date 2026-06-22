<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Personel arayüz teması (admin · kasa · garson)
    |--------------------------------------------------------------------------
    |
    | premium — koyu yeşil / altın şablon (mockup)
    | classic — önceki açık admin + koyu garson görünümü
    |
    | Geri almak için .env: STAFF_UI_THEME=classic
    |
    */
    'staff_theme' => env('STAFF_UI_THEME', 'premium'),

];
