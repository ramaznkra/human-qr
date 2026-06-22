@extends('layouts.admin')
@php
    $roleLabels = [
        \App\Models\User::ROLE_ADMIN => 'Admin',
        \App\Models\User::ROLE_CASHIER => 'Kasa',
        \App\Models\User::ROLE_WAITER => 'Garson',
    ];
    $currentRole = old('role', $waiter->role ?? \App\Models\User::ROLE_WAITER);
    $roleLabel = $roleLabels[$currentRole] ?? 'Personel';
@endphp
@section('title', $waiter->exists ? $roleLabel.' Düzenle' : 'Yeni Personel')
@section('page_heading', $waiter->exists ? $roleLabel.' Düzenle' : 'Yeni Personel')
@section('section_label', 'Personel')
@section('content')
<div class="mb-6">
    <h2 class="admin-page-title">{{ $waiter->exists ? $roleLabel.' Düzenle' : 'Yeni Personel Ekle' }}</h2>
    <p class="mt-1 admin-text-muted">
        Admin hesapları yönetim paneline; garsonlar mobil garson paneline; kasa hesapları canlı sipariş ekranına erişir.
    </p>
</div>

<div class="admin-card max-w-xl">
    <form method="POST" action="{{ $waiter->exists ? route('admin.waiters.update', $waiter) : route('admin.waiters.store') }}" class="space-y-4">
        @csrf
        @if($waiter->exists) @method('PUT') @endif
        @include('admin.waiters.partials.form-fields', ['waiter' => $waiter])
        <div class="flex gap-3 pt-2">
            <button type="submit" class="btn btn-primary">{{ $waiter->exists ? 'Kaydet' : 'Hesap Ekle' }}</button>
            <a href="{{ route('admin.waiters.index') }}" class="btn btn-secondary">İptal</a>
        </div>
    </form>
</div>
@endsection
