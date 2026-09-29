<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} — {{ $title ?? __('messages.navigation.dashboard') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* Stable map shell: header and right sidebar own the viewport edges;
           the map occupies only the remaining rectangle. */
        .map-page {
            overflow: hidden;
        }

        .map-page .main-content {
            position: relative !important;
            top: auto !important;
            right: auto !important;
            left: auto !important;
            bottom: auto !important;
            width: auto !important;
            height: 100vh !important;
            min-height: 0 !important;
            margin: 0 16rem 0 0 !important;
            padding: 4rem 0 0 0 !important;
            overflow: hidden !important;
            box-sizing: border-box;
            z-index: 1;
        }

        .map-page .map-shell {
            position: relative;
            width: 100%;
            height: 100% !important;
            min-height: 0 !important;
            overflow: hidden;
        }

        .map-page #map {
            width: 100%;
            height: 100% !important;
            min-height: 0 !important;
        }

        .map-page footer {
            display: none;
        }

        /* Sidebar is on the right in RTL. Keep the tools on the left. */
        .map-page .map-tool-dock {
            left: 16px !important;
            right: auto !important;
        }

        .map-page #sidebar {
            right: 0 !important;
            left: auto !important;
            transform: translateX(0) !important;
            z-index: 1200 !important;
        }

        .map-page .map-legend {
            left: 16px !important;
            right: auto !important;
        }

        @media (max-width: 1023px) {
            .map-page .main-content {
                width: 100% !important;
                height: 100vh !important;
                margin: 0 !important;
                padding: 4rem 0 0 0 !important;
            }

            .map-page .map-shell,
            .map-page #map {
                height: 100% !important;
                min-height: 0 !important;
            }

            .map-page .map-tool-dock {
                left: 10px !important;
                right: 10px !important;
                width: auto !important;
                max-width: none !important;
                max-height: calc(100% - 20px) !important;
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

    <main class="main-content min-w-0 pt-16 lg:mr-64">
        @if (session('success'))<div class="mx-auto mt-4 max-w-7xl px-4 sm:px-6 lg:px-8"><div class="enter-alert rounded-md border border-success bg-success-surface px-4 py-3 text-success" role="alert">{{ session('success') }}</div></div>@endif
        @if (session('warning'))<div class="mx-auto mt-4 max-w-7xl px-4 sm:px-6 lg:px-8"><div class="enter-alert rounded-md border border-warning bg-warning-surface px-4 py-3 text-warning" role="alert">{{ session('warning') }}</div></div>@endif
        @if (session('error'))<div class="mx-auto mt-4 max-w-7xl px-4 sm:px-6 lg:px-8"><div class="enter-alert rounded-md border border-danger bg-danger-surface px-4 py-3 text-danger" role="alert">{{ session('error') }}</div></div>@endif
        @yield('content')
    </main>

    <footer class="border-t border-border bg-white py-4 text-center text-xs text-ink-muted lg:mr-64">{{ __('messages.app.municipality') }} — {{ __('messages.app.department') }} © {{ date('Y') }}</footer>

    @stack('scripts')
</body>
</html>
