@extends('layouts.admin')
@section('title', 'Personel')
@section('page_heading', 'Personel Hesapları')
@section('section_label', 'Personel')
@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
        <p class="admin-text-muted">Admin, garson ve kasa hesapları <strong class="text-zinc-300">/admin/giris</strong> üzerinden kendi panellerine girer.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.waiters.create', ['role' => \App\Models\User::ROLE_ADMIN]) }}" class="btn btn-primary">+ Admin</a>
        <a href="{{ route('admin.waiters.create', ['role' => \App\Models\User::ROLE_WAITER]) }}" class="btn btn-secondary">+ Garson</a>
        <a href="{{ route('admin.waiters.create', ['role' => \App\Models\User::ROLE_CASHIER]) }}" class="btn btn-secondary">+ Kasa</a>
    </div>
</div>

@if($waiters->isEmpty())
<div class="admin-card py-12 text-center text-zinc-500">
    Henüz personel hesabı yok.
    <a href="{{ route('admin.waiters.create') }}" class="admin-link-gold">İlk hesabı ekleyin</a>.
</div>
@else
<div class="admin-card overflow-hidden p-0">
    <div class="overflow-x-auto">
        <table class="admin-table w-full min-w-[720px]">
            <thead>
                <tr>
                    <th>Ad</th>
                    <th class="admin-table-col-center">Rol</th>
                    <th>E-posta</th>
                    <th class="admin-table-col-center">Durum</th>
                    <th class="text-right">İşlem</th>
                </tr>
            </thead>
            <tbody>
                @foreach($waiters as $waiter)
                <tr
                    class="transition {{ $waiter->is_active ? '' : 'opacity-60' }}"
                    data-waiter-item
                    data-waiter-id="{{ $waiter->id }}"
                >
                    <td>
                        <p class="font-medium text-zinc-200">{{ $waiter->name }}</p>
                        <p class="text-xs text-zinc-500">ID #{{ $waiter->id }}</p>
                    </td>
                    <td class="admin-table-col-center">
                        @if($waiter->isAdmin())
                        <span class="inline-flex rounded-full border border-[#C6A046]/25 bg-[#C6A046]/10 px-2.5 py-0.5 text-xs font-semibold text-[#C6A046]">Admin</span>
                        @elseif($waiter->isCashier())
                        <span class="inline-flex rounded-full border border-amber-500/25 bg-amber-500/10 px-2.5 py-0.5 text-xs font-semibold text-amber-300">Kasa</span>
                        @else
                        <span class="inline-flex rounded-full border border-sky-500/25 bg-sky-500/10 px-2.5 py-0.5 text-xs font-semibold text-sky-300">Garson</span>
                        @endif
                    </td>
                    <td class="font-mono text-sm text-zinc-300">{{ $waiter->email }}</td>
                    <td class="admin-table-col-center">
                        <div class="admin-toggle-wrap flex items-center justify-center gap-3">
                            <label class="relative inline-flex shrink-0 cursor-pointer items-center" title="Hesabı aç / kapat">
                                <input
                                    type="checkbox"
                                    class="peer sr-only"
                                    data-waiter-toggle
                                    data-toggle-url="{{ route('admin.waiters.toggle-active', $waiter) }}"
                                    {{ $waiter->is_active ? 'checked' : '' }}
                                    aria-label="{{ $waiter->name }} aktif"
                                >
                                <span class="admin-toggle__track admin-toggle__track--emerald"></span>
                            </label>
                            <span
                                class="shrink-0 text-xs font-medium {{ $waiter->is_active ? 'text-emerald-400' : 'text-zinc-500' }}"
                                data-waiter-status-label
                            >{{ $waiter->is_active ? 'Aktif' : 'Pasif' }}</span>
                        </div>
                    </td>
                    <td class="text-right">
                        <div class="inline-flex justify-end gap-2">
                            <button type="button" class="btn btn-sm btn-secondary" data-admin-drawer-open="{{ route('admin.waiters.panel', $waiter) }}">Düzenle</button>
                            <form action="{{ route('admin.waiters.destroy', $waiter) }}" method="POST" onsubmit="return confirm('Bu {{ strtolower($waiter->staffRoleLabel()) }} hesabı silinsin mi?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-secondary text-red-400 hover:border-red-900/50 hover:bg-red-950/30">Sil</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection

@push('scripts')
@vite('resources/js/pages/admin-waiters.js')
@endpush
