<div id="kasaLineItemModal" class="lo-kasa-opt-modal lo-kasa-line-modal" aria-hidden="true" inert>
    <div class="lo-kasa-opt-modal__backdrop" data-kasa-line-close></div>
    <div class="lo-kasa-opt-modal__panel lo-kasa-line-modal__panel" role="dialog" aria-labelledby="kasaLineItemTitle">
        <header class="lo-kasa-opt-modal__head">
            <div class="min-w-0">
                <h2 id="kasaLineItemTitle" class="lo-kasa-opt-modal__title"></h2>
                <p id="kasaLineItemPrice" class="lo-kasa-opt-modal__base"></p>
            </div>
            <button type="button" class="lo-kasa-opt-modal__close" data-kasa-line-close aria-label="Kapat">×</button>
        </header>
        <div class="lo-kasa-line-modal__body">
            <p class="lo-kasa-line-modal__label">Adet</p>
            <div class="lo-kasa-line-modal__qty">
                <button type="button" id="kasaLineItemMinus" class="lo-kasa-line-modal__qty-btn lo-kasa-touch" aria-label="Azalt">−</button>
                <span id="kasaLineItemQty" class="lo-kasa-line-modal__qty-value select-none">1</span>
                <button type="button" id="kasaLineItemPlus" class="lo-kasa-line-modal__qty-btn lo-kasa-touch" aria-label="Artır">+</button>
            </div>
        </div>
        <p id="kasaLineItemError" class="lo-kasa-opt-modal__error hidden"></p>
        <footer class="lo-kasa-opt-modal__foot lo-kasa-line-modal__foot">
            <button type="button" id="kasaLineItemRemove" class="lo-kasa-line-modal__remove">Ürünü İptal Et</button>
            <button type="button" class="lo-kasa-opt-modal__cancel" data-kasa-line-close>Kapat</button>
        </footer>
    </div>
</div>
