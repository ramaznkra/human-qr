<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ProductOptionPricing
{
    /**
     * @param  array<int, array{group_id?: int, option_id?: int}>  $selectedOptions
     * @return array{
     *     unit_price: string,
     *     options: array<int, array{group_id: int, group_name: string, option_id: int, name: string, price: string}>,
     *     display_name_suffix: string
     * }
     */
    public function resolve(Product $product, array $selectedOptions, ?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();
        $product->loadMissing(['optionGroups.options']);

        /** @var Collection<int, ProductOptionGroup> $groups */
        $groups = $product->optionGroups;

        if ($groups->isEmpty()) {
            return [
                'unit_price' => Money::normalize($product->price),
                'options' => [],
                'display_name_suffix' => '',
            ];
        }

        $byGroup = collect($selectedOptions)
            ->filter(fn ($row) => isset($row['group_id'], $row['option_id']))
            ->groupBy(fn ($row) => (int) $row['group_id']);

        $resolved = [];
        $suffixParts = [];

        foreach ($groups as $group) {
            $rows = $byGroup->get($group->id, collect());
            $optionIds = $rows
                ->pluck('option_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($group->type === ProductOptionGroup::TYPE_SINGLE) {
                if ($group->required && $optionIds->isEmpty()) {
                    throw ValidationException::withMessages([
                        'items' => sprintf('"%s" seçimi zorunludur.', $group->localizedName($locale)),
                    ]);
                }

                if ($optionIds->count() > 1) {
                    throw ValidationException::withMessages([
                        'items' => sprintf('"%s" için yalnızca bir seçenek seçilebilir.', $group->localizedName($locale)),
                    ]);
                }

                if ($optionIds->isEmpty()) {
                    continue;
                }

                $option = $this->findGroupOption($group, $optionIds->first());
                $resolved[] = $this->formatOption($group, $option, $locale);
                $suffixParts[] = $option->localizedName($locale);
            } else {
                if ($group->required && $optionIds->isEmpty()) {
                    throw ValidationException::withMessages([
                        'items' => sprintf('"%s" için en az bir seçim yapın.', $group->localizedName($locale)),
                    ]);
                }

                foreach ($optionIds as $optionId) {
                    $option = $this->findGroupOption($group, $optionId);
                    $resolved[] = $this->formatOption($group, $option, $locale);
                    $suffixParts[] = $option->localizedName($locale);
                }
            }
        }

        $allowedGroupIds = $groups->pluck('id')->all();
        $unknownGroups = $byGroup->keys()
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => in_array($id, $allowedGroupIds, true));

        if ($unknownGroups->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Geçersiz ürün seçeneği.',
            ]);
        }

        $optionsTotal = Money::sum(array_column($resolved, 'price'));

        return [
            'unit_price' => Money::add($product->price, $optionsTotal),
            'options' => $resolved,
            'display_name_suffix' => $suffixParts !== [] ? ' ('.implode(', ', $suffixParts).')' : '',
        ];
    }

    private function findGroupOption(ProductOptionGroup $group, int $optionId): ProductOption
    {
        $option = $group->options->firstWhere('id', $optionId);

        if (! $option) {
            throw ValidationException::withMessages([
                'items' => 'Seçilen ürün seçeneği geçersiz veya artık mevcut değil.',
            ]);
        }

        if ($option->is_active === false) {
            throw ValidationException::withMessages([
                'items' => 'Seçilen seçenek şu an kullanılamıyor.',
            ]);
        }

        return $option;
    }

    /**
     * @return array{group_id: int, group_name: string, option_id: int, name: string, price: string}
     */
    private function formatOption(ProductOptionGroup $group, ProductOption $option, string $locale): array
    {
        return [
            'group_id' => $group->id,
            'group_name' => $group->localizedName($locale),
            'option_id' => $option->id,
            'name' => $option->localizedName($locale),
            'price' => Money::normalize($option->price_modifier),
        ];
    }
}
