@extends('layouts.admin')

@section('title', 'Canlı Siparişler')
@section('page_heading', 'Canlı Siparişler')

@section('content')
@include('admin.live-orders._shell', [
    'fullscreen' => false,
    'showSidebar' => true,
    'tables' => $tables,
    'busyTableIds' => $busyTableIds,
    'categories' => $categories ?? collect(),
    'kasaLogo' => $kasaLogo ?? null,
])
@endsection

@push('scripts')
@vite('resources/js/pages/live-orders.js')
@endpush
