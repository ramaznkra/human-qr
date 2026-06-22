@php
    $roleLabels = [
        \App\Models\User::ROLE_ADMIN => 'Admin',
        \App\Models\User::ROLE_CASHIER => 'Kasa',
        \App\Models\User::ROLE_WAITER => 'Garson',
    ];
    $currentRole = old('role', $waiter->role ?? \App\Models\User::ROLE_WAITER);
    $inDrawer = $inDrawer ?? false;
@endphp
<div>
    <label class="form-label">Rol *</label>
    <select name="role" class="form-input max-w-none" required>
        <option value="{{ \App\Models\User::ROLE_ADMIN }}" @selected($currentRole === \App\Models\User::ROLE_ADMIN)>Admin</option>
        <option value="{{ \App\Models\User::ROLE_WAITER }}" @selected($currentRole === \App\Models\User::ROLE_WAITER)>Garson</option>
        <option value="{{ \App\Models\User::ROLE_CASHIER }}" @selected($currentRole === \App\Models\User::ROLE_CASHIER)>Kasa</option>
    </select>
    @error('role')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
</div>

<div>
    <label class="form-label">Ad Soyad *</label>
    <input type="text" name="name" value="{{ old('name', $waiter->name) }}" required class="form-input max-w-none" placeholder="Ahmet Yılmaz">
    @error('name')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
</div>

<div>
    <label class="form-label">E-posta (giriş) *</label>
    <input type="email" name="email" value="{{ old('email', $waiter->email) }}" required class="form-input max-w-none" placeholder="ornek@human.com" autocomplete="username">
    @error('email')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
</div>

<div>
    <label class="form-label">{{ $waiter->exists ? 'Yeni Şifre' : 'Şifre *' }}</label>
    <input
        type="password"
        name="password"
        class="form-input max-w-none"
        {{ $waiter->exists ? '' : 'required' }}
        minlength="8"
        autocomplete="new-password"
        placeholder="{{ $waiter->exists ? 'Boş bırakırsanız değişmez' : 'En az 8 karakter' }}"
    >
    @error('password')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
</div>

@if($waiter->exists && ! $inDrawer)
<p class="text-xs text-zinc-400">Aktif/pasif durumunu <a href="{{ route('admin.waiters.index') }}" class="admin-link-gold">Personel</a> listesindeki anahtardan değiştirebilirsiniz.</p>
@endif
