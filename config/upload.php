<?php

return [

    /*
    | Ürün görseli yükleme limiti (kilobayt). Laravel validation: max:N
    | PHP upload_max_filesize / post_max_size bu değerden büyük olmalı.
    */
    'product_image_max_kb' => (int) env('PRODUCT_IMAGE_MAX_KB', 10240),

];
