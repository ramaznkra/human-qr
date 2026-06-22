@php
    $confirmConfig = [
        'title' => $title ?? 'Emin misiniz?',
        'message' => $message ?? '',
        'hint' => $hint ?? '',
        'type' => $type ?? 'warning',
        'confirmLabel' => $confirmLabel ?? 'Evet',
        'cancelLabel' => $cancelLabel ?? 'Vazgeç',
    ];
@endphp
data-admin-confirm
data-confirm-config="{{ e(json_encode($confirmConfig, JSON_HEX_APOS | JSON_HEX_QUOT)) }}"
