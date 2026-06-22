@props(['scope' => 'catalog'])
<div class="admin-view-toggle" role="group" aria-label="Görünüm">
    <button
        type="button"
        class="admin-view-toggle__btn"
        data-view-mode="tray"
        data-view-scope="{{ $scope }}"
        aria-pressed="true"
    >Tepsi</button>
    <button
        type="button"
        class="admin-view-toggle__btn"
        data-view-mode="list"
        data-view-scope="{{ $scope }}"
        aria-pressed="false"
    >Liste</button>
</div>
