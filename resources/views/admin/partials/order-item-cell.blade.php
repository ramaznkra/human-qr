@props(['item'])
<div class="order-item-cell min-w-0">
    <span class="font-medium text-zinc-200">{{ $item->product_name }}</span>
    @if($item->optionLabelLines() !== [])
    <p class="mt-0.5 text-xs italic text-zinc-500">{{ implode(' · ', $item->optionLabelLines()) }}</p>
    @endif
    @if($item->notes)
    <p class="mt-0.5 text-xs text-zinc-500">{{ $item->notes }}</p>
    @endif
</div>
