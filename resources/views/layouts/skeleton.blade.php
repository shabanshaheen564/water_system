<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} — {{ $title ?? __('messages.navigation.dashboard') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="min-h-screen bg-white text-ink">
    <div id="sidebar-overlay" class="fixed inset-0 z-40 hidden bg-black/30 opacity-0 transition-opacity duration-[220ms] lg:hidden" aria-hidden="true"></div>

    {{-- Stable application skeleton: header + side menu + changing body --}}
    @include('layouts.side_menu')
    @include('layouts.header')

    <main class="main-content min-w-0 pt-16 lg:ms-64">
        @if (session('success'))<div class="mx-auto mt-4 max-w-7xl px-4 sm:px-6 lg:px-8"><div class="enter-alert rounded-md border border-success bg-success-surface px-4 py-3 text-success" role="alert">{{ session('success') }}</div></div>@endif
        @if (session('warning'))<div class="mx-auto mt-4 max-w-7xl px-4 sm:px-6 lg:px-8"><div class="enter-alert rounded-md border border-warning bg-warning-surface px-4 py-3 text-warning" role="alert">{{ session('warning') }}</div></div>@endif
        @if (session('error'))<div class="mx-auto mt-4 max-w-7xl px-4 sm:px-6 lg:px-8"><div class="enter-alert rounded-md border border-danger bg-danger-surface px-4 py-3 text-danger" role="alert">{{ session('error') }}</div></div>@endif
        @yield('content')
    </main>

    <footer class="border-t border-border bg-white py-4 text-center text-xs text-ink-muted">{{ __('messages.app.municipality') }} — {{ __('messages.app.department') }} © {{ date('Y') }}</footer>

    @stack('scripts')
</body>
</html>