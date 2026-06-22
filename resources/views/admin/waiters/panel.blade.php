@php
    $roleLabels = [
        \App\Models\User::ROLE_ADMIN => 'Admin',
        \App\Models\User::ROLE_CASHIER => 'Kasa',
        \App\Models\User::ROLE_WAITER => 'Garson',
    ];
    $roleLabel = $roleLabels[$waiter->role] ?? 'Personel';
@endphp
<div class="admin-drawer-form">
    <h2 class="mb-1 text-lg font-bold text-zinc-100">{{ $roleLabel }} Düzenle</h2>
    <p class="mb-4 text-sm font-medium text-zinc-400">{{ $waiter->name }}</p>

    <form
        method="POST"
        action="{{ route('admin.waiters.update', $waiter) }}"
        class="space-y-4"
        data-admin-drawer-form
    >
        @csrf
        @method('PUT')
        @include('admin.waiters.partials.form-fields', ['waiter' => $waiter, 'inDrawer' => true])
        <div class="admin-drawer-form__actions sticky bottom-0 flex gap-2 border-t border-zinc-900 bg-[#111111] pt-4">
            <button type="submit" class="btn btn-primary flex-1">Kaydet</button>
            <button type="button" class="btn btn-secondary" data-admin-drawer-close>İptal</button>
        </div>
    </form>
</div>
