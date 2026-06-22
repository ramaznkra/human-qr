<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Support\Collection;

class OrderStationFlags
{
    /**
     * @param  Collection<int, string>|array<int, string>  $types
     * @return array{has_kitchen: bool, has_bar: bool, has_hookah: bool, has_nargile: bool, has_service: bool, has_retail: bool}
     */
    public static function fromTypes(Collection|array $types): array
    {
        $collection = $types instanceof Collection ? $types : collect($types);

        return [
            'has_kitchen' => $collection->contains(Category::TYPE_KITCHEN),
            'has_bar' => $collection->contains(Category::TYPE_BAR),
            'has_hookah' => $collection->contains('hookah') || $collection->contains(Category::TYPE_NARGILE),
            'has_nargile' => $collection->contains('hookah') || $collection->contains(Category::TYPE_NARGILE),
            'has_service' => $collection->contains('service') || $collection->contains(Category::TYPE_RETAIL),
            'has_retail' => $collection->contains('service') || $collection->contains(Category::TYPE_RETAIL),
        ];
    }
}
