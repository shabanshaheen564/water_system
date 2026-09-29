<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} — {{ $title ?? __('messages.navigation.dashboard') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* The map is a full-height application surface. It must live inside the
           stable shell instead of competing with the global header/footer. */
        .map-page .main-content {
            height: calc(100vh - 4rem);
            min-height: calc(100vh - 4rem);
            overflow: hidden;
            padding: 0 !important;
        }
        .map-page footer { display: none; }
        .map-page .map-shell {
            width: 100%;
            height: calc(100vh - 4rem) !important;
            min-height: 0 !important;
            overflow: hidden;
        }
        .map-page #map {
            width: 100%;
            height: 100% !important;
            min-height: 0 !important;
        }
        /* In RTL, inline-start is the right side. Keep the map tools on the
           left so they never collide with the application's right sidebar. */
        .map-page .map-tool-dock {
            inset-inline-start: auto !important;
            inset-inline-end: 16px !important;
        }
        .map-page .map-legend {
            inset-inline-end: 16px !important;
        }
        @media (max-width: 1023px) {
            .map-page .main-content,
            .map-page .map-shell {
                height: calc(100vh - 4rem) !important;
                min-height: 0 !important;
            }
            .map-page .map-tool-dock {
                inset-inline: 10px !important;
                width: auto !important;
                max-width: none !important;
            }
        }
    </style>
    @stack('styles')
</head>
<body class="min-h-screen bg-white text-ink @if(request()->routeIs('map.*')) map-page @endif">
    <div id="sidebar-overlay" class="fixed inset-0 z-40 hidden bg-black/30 opacity-0 transition-opacity duration-[220ms] lg:hidden" aria-hidden="true"></div>

    {{-- Stable application skeleton: fixed header + fixed right sidebar + changing body --}}
    @include('layouts.side_menu')
    @include('layouts.header')

    <main class="main-content min-w-0 pt-16 lg:me-64">
        @if (session('success'))<div class="mx-auto mt-4 max-w-7xl px-4 sm:px-6 lg:px-8"><div class="enter-alert rounded-md border border-success bg-success-surface px-4 py-3 text-success" role="alert">{{ session('success') }}</div></div>@endif
        @if (session('warning'))<div class="mx-auto mt-4 max-w-7xl px-4 sm:px-6 lg:px-8"><div class="enter-alert rounded-md border border-warning bg-warning-surface px-4 py-3 text-warning" role="alert">{{ session('warning') }}</div></div>@endif
        @if (session('error'))<div class="mx-auto mt-4 max-w-7xl px-4 sm:px-6 lg:px-8"><div class="enter-alert rounded-md border border-danger bg-danger-surface px-4 py-3 text-danger" role="alert">{{ session('error') }}</div></div>@endif
        @yield('content')
    </main>

    <footer class="border-t border-border bg-white py-4 text-center text-xs text-ink-muted lg:me-64">{{ __('messages.app.municipality') }} — {{ __('messages.app.department') }} © {{ date('Y') }}</footer>

    @stack('scripts')
</body>
</html>
