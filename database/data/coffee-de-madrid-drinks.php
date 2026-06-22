<?php

/**
 * Coffee de Madrid reçete kitapçığı (02.2026) — içecek kodları ve isimler.
 * İçecek kategorisinde yalnızca bu liste kullanılır.
 */
return [
    // Espresso bazlı
    ['name' => 'Espresso', 'section' => 'espresso-based', 'price' => 75],
    ['name' => 'Americano', 'section' => 'espresso-based', 'price' => 85],
    ['name' => 'Cortado', 'section' => 'espresso-based', 'price' => 90],
    ['name' => 'Red Eye', 'section' => 'espresso-based', 'price' => 95, 'desc' => 'Filtre kahve üzerine espresso'],
    ['name' => 'Black Eye', 'section' => 'espresso-based', 'price' => 100, 'desc' => 'Çift shot filtre kahve'],
    ['name' => 'Dead Eye', 'section' => 'espresso-based', 'price' => 110, 'desc' => 'Üçlü shot filtre kahve'],

    // Filtre
    ['name' => 'Sade Filtre Kahve', 'section' => 'filter-coffee', 'price' => 80, 'code' => 'BC'],
    ['name' => 'Sütlü Filtre Kahve', 'section' => 'filter-coffee', 'price' => 90, 'desc' => 'Misto', 'code' => 'MİS'],

    // Sütlü kahveler
    ['name' => 'Latte', 'section' => 'milk-coffee', 'price' => 95, 'variations' => true],
    ['name' => 'Flat White', 'section' => 'milk-coffee', 'price' => 105, 'variations' => true],
    ['name' => 'Cappuccino', 'section' => 'milk-coffee', 'price' => 95, 'variations' => true],
    ['name' => 'Mocha', 'section' => 'milk-coffee', 'price' => 110, 'variations' => true],
    ['name' => 'White Chocolate Mocha', 'section' => 'milk-coffee', 'price' => 115, 'variations' => true],
    ['name' => 'Caramel Macchiato', 'section' => 'milk-coffee', 'price' => 115, 'variations' => true],
    ['name' => 'Toffee Nut Latte', 'section' => 'milk-coffee', 'price' => 120, 'variations' => true],
    ['name' => 'Salted Caramel Latte', 'section' => 'milk-coffee', 'price' => 120, 'desc' => 'Tuzlu karamel latte', 'variations' => true],
    ['name' => 'Biscoff Latte', 'section' => 'milk-coffee', 'price' => 120, 'variations' => true],
    ['name' => 'Raspberry White Mocha', 'section' => 'milk-coffee', 'price' => 125, 'variations' => true],
    ['name' => 'Maple Cream Latte', 'section' => 'milk-coffee', 'price' => 120, 'variations' => true],
    ['name' => 'Tiramisu Latte', 'section' => 'milk-coffee', 'price' => 125, 'variations' => true],
    ['name' => 'Boomer Latte', 'section' => 'milk-coffee', 'price' => 120, 'variations' => true],
    ['name' => 'Kıtkat Latte', 'section' => 'milk-coffee', 'price' => 125, 'variations' => true],
    ['name' => 'Fıstıklı Latte', 'section' => 'milk-coffee', 'price' => 120, 'variations' => true],
    ['name' => 'Fındıklı Latte', 'section' => 'milk-coffee', 'price' => 120, 'variations' => true],
    ['name' => 'Chai Tea Latte', 'section' => 'milk-coffee', 'price' => 95, 'variations' => true],

    // Sıcak içecekler (kahve dışı sıcak — kitapçık kodları)
    ['name' => 'White Hot Chocolate', 'section' => 'hot-special', 'price' => 100, 'variations' => true],
    ['name' => 'Sıcak Çikolata', 'section' => 'hot-special', 'price' => 95, 'variations' => true],
    ['name' => 'Salep', 'section' => 'hot-special', 'price' => 90],

    // Vegan / yulaf
    ['name' => 'Strawberry Oat Latte', 'section' => 'vegan-drinks', 'price' => 125, 'variations' => true],
    ['name' => 'Roasted Almond Oat Latte', 'section' => 'vegan-drinks', 'price' => 125, 'variations' => true],
    ['name' => 'Harmony Latte', 'section' => 'vegan-drinks', 'price' => 120, 'variations' => true],

    // Frappe — kahve bazlı
    ['name' => 'Coffee Frappe', 'section' => 'frappe-coffee', 'price' => 120],
    ['name' => 'Mocha Frappe', 'section' => 'frappe-coffee', 'price' => 125],
    ['name' => 'Caramel Frappe', 'section' => 'frappe-coffee', 'price' => 125],
    ['name' => 'Hazelnut Frappe', 'section' => 'frappe-coffee', 'price' => 125],

    // Frappe — cream bazlı
    ['name' => 'Caramel Cream Frappe', 'section' => 'frappe-cream', 'price' => 130],
    ['name' => 'Strawberry Cream Frappe', 'section' => 'frappe-cream', 'price' => 130],
    ['name' => 'Chocolate Cream Frappe', 'section' => 'frappe-cream', 'price' => 130],
    ['name' => 'Pistachio Cream Frappe', 'section' => 'frappe-cream', 'price' => 135],
];
