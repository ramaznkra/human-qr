<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use App\Support\CurrentRestaurant;
use App\Support\MenuTranslations;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductOptionSyncService
{
    /**
     * @param  array<int, array<string, mixed>>|null  $groupsInput
     */
    public function sync(Product $product, ?array $groupsInput): void
    {
        $groupsInput = array_values($groupsInput ?? []);

        DB::transaction(function () use ($product, $groupsInput) {
            $keptGroupIds = [];

            foreach ($groupsInput as $index => $groupData) {
                $optionsInput = array_values($groupData['options'] ?? []);
                if ($optionsInput === []) {
                    continue;
                }

                $group = $this->upsertGroup($product, $groupData, $index);
                $keptGroupIds[] = $group->id;

                $keptOptionIds = [];
                foreach ($optionsInput as $optIndex => $optionData) {
                    $option = $this->upsertOption($group, $optionData, $optIndex);
                    $keptOptionIds[] = $option->id;
                }

                $group->options()->whereNotIn('id', $keptOptionIds)->delete();
                $this->enforceDefaultRules($group->fresh('options'));
            }

            $product->optionGroups()->whereNotIn('id', $keptGroupIds)->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function upsertGroup(Product $product, array $data, int $sortOrder): ProductOptionGroup
    {
        $name = MenuTranslations::cleanMap($data['name'] ?? []);
        if ($name === []) {
            throw ValidationException::withMessages([
                'option_groups' => 'Her varyasyon grubunun Türkçe adı zorunludur.',
            ]);
        }

        $type = $data['type'] ?? ProductOptionGroup::TYPE_SINGLE;
        if (! in_array($type, [ProductOptionGroup::TYPE_SINGLE, ProductOptionGroup::TYPE_MULTIPLE], true)) {
            $type = ProductOptionGroup::TYPE_SINGLE;
        }

        $attrs = [
            'name' => $name,
            'type' => $type,
            'required' => filter_var($data['required'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'sort_order' => (int) ($data['sort_order'] ?? $sortOrder),
        ];

        if (! empty($data['id'])) {
            $group = ProductOptionGroup::query()
                ->where('product_id', $product->id)
                ->find($data['id']);

            if ($group) {
                $group->update($attrs);

                return $group;
            }
        }

        return ProductOptionGroup::create(array_merge($attrs, [
            'product_id' => $product->id,
            'restaurant_id' => $product->restaurant_id ?? CurrentRestaurant::resolveId(),
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function upsertOption(ProductOptionGroup $group, array $data, int $sortOrder): ProductOption
    {
        $name = MenuTranslations::cleanMap($data['name'] ?? []);
        if ($name === []) {
            throw ValidationException::withMessages([
                'option_groups' => 'Her seçeneğin Türkçe adı zorunludur.',
            ]);
        }

        $attrs = [
            'name' => $name,
            'price_modifier' => Money::max('0', $data['price_modifier'] ?? 0),
            'is_active' => filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'is_default' => filter_var($data['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'sort_order' => (int) ($data['sort_order'] ?? $sortOrder),
        ];

        if (! empty($data['id'])) {
            $option = ProductOption::query()
                ->where('product_option_group_id', $group->id)
                ->find($data['id']);

            if ($option) {
                $option->update($attrs);

                return $option;
            }
        }

        return ProductOption::create(array_merge($attrs, [
            'product_option_group_id' => $group->id,
        ]));
    }

    private function enforceDefaultRules(ProductOptionGroup $group): void
    {
        $options = $group->options;
        if ($options->isEmpty()) {
            return;
        }

        if ($group->type !== ProductOptionGroup::TYPE_SINGLE) {
            return;
        }

        $defaults = $options->where('is_default', true);

        if ($defaults->isEmpty()) {
            $options->first()->update(['is_default' => true]);

            return;
        }

        if ($defaults->count() > 1) {
            $keepId = $defaults->first()->id;
            foreach ($options as $option) {
                $option->update(['is_default' => $option->id === $keepId]);
            }
        }
    }
}
