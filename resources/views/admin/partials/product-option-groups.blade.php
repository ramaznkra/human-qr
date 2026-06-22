@php
    /** @var \App\Models\Product $product */
    $optionGroups = old('option_groups');

    if ($optionGroups === null && $product->exists) {
        $product->loadMissing(['optionGroups.options']);
        $optionGroups = $product->optionGroups->map(fn ($group) => [
            'id' => $group->id,
            'name' => $group->getTranslations('name'),
            'type' => $group->type,
            'required' => $group->required,
            'sort_order' => $group->sort_order,
            'options' => $group->options->map(fn ($option) => [
                'id' => $option->id,
                'name' => $option->getTranslations('name'),
                'price_modifier' => $option->price_modifier,
                'is_active' => $option->is_active,
                'is_default' => $option->is_default,
                'sort_order' => $option->sort_order,
            ])->values()->all(),
        ])->values()->all();
    }

    $optionGroups = is_array($optionGroups) ? array_values($optionGroups) : [];
    $formId = $formId ?? null;
@endphp

<section class="variation-panel border border-[#C6A046]/20 bg-[#111111] p-0" data-product-options>
    <div class="variation-panel__head border-b border-zinc-900 px-5 py-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-widest text-[#C6A046]">Varyasyon Yönetim Paneli</p>
                <h3 class="mt-1 text-base font-semibold text-zinc-100">Dinamik Seçenekler</h3>
                <p class="mt-1 text-xs text-zinc-400">Büyük/Küçük Boy, Ekstra Sos gibi grupları JSON olarak kaydedilir; menü ve sipariş akışında kullanılır.</p>
            </div>
            <button type="button" class="btn btn-primary btn-sm shrink-0" data-add-option-group>+ Grup Ekle</button>
        </div>
    </div>

    <div class="space-y-4 p-5" data-option-groups-list>
        @foreach($optionGroups as $gi => $group)
            @include('admin.partials.product-option-group-row', ['gi' => $gi, 'group' => $group, 'formId' => $formId])
        @endforeach
    </div>

    <div class="retail-variation-banner hidden border-t border-[#C6A046]/20 bg-[#0A0A0A]/80 px-5 py-4" data-retail-variation-banner>
        <p class="text-sm font-semibold text-zinc-100">Biblo / Figür — Boy Varyasyonu</p>
        <p class="mt-1 text-xs text-zinc-400">Ana fiyata eklenecek farkları (+150 ₺, +300 ₺) JSON olarak kaydedilir.</p>
        <button type="button" class="btn btn-secondary btn-sm mt-3" data-apply-retail-boy-template>Boy Şablonu Uygula (Küçük / Büyük)</button>
    </div>

    <p class="border-t border-zinc-900 px-5 py-4 text-xs text-zinc-500" data-option-groups-empty {{ count($optionGroups) ? 'hidden' : '' }}>
        Henüz varyasyon yok. Örn. “Boy” grubu altında Normal / Büyük, “Sos” grubu altında Ketçap / Mayonez ekleyebilirsiniz.
    </p>

    <template data-option-group-template>
        @include('admin.partials.product-option-group-row', [
            'gi' => '__GI__',
            'group' => ['type' => 'single', 'required' => false, 'options' => []],
            'formId' => $formId,
        ])
    </template>

    <template data-option-row-template>
        @include('admin.partials.product-option-row', [
            'gi' => '__GI__',
            'oi' => '__OI__',
            'option' => ['price_modifier' => 0, 'is_default' => false, 'is_active' => true],
            'groupType' => 'single',
            'formId' => $formId,
        ])
    </template>
</section>
