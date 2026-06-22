@props(['label', 'url'])
<div class="admin-secret-url">
    <label class="form-label">{{ $label }}</label>
    <div class="admin-secret-url__row">
        <input type="text" readonly value="{{ $url }}" class="admin-secret-url__input form-input" aria-label="{{ $label }}">
        <button type="button" class="admin-secret-url__copy" data-copy-target="prev" title="Linki kopyala" aria-label="Linki kopyala">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
        </button>
    </div>
</div>
