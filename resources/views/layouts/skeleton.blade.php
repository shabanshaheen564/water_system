<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} — {{ $title ?? __('messages.navigation.dashboard') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* The application shell owns the page geometry. The map is the only
           page that is allowed to fill the remaining viewport below the header. */
        .map-page {
            overflow: hidden;
        }

        .map-page .main-content {
            position: fixed;
            top: 4rem;
            right: 0;
            left: 16rem;
            bottom: 0;
            width: auto;
            height: auto;
            min-height: 0;
            margin: 0 !important;
            padding: 0 !important;
            overflow: hidden;
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

        /* The application sidebar is on the RIGHT in RTL. Map controls live
           on the LEFT so they never overlap the sidebar. */
        .map-page .map-tool-dock {
            inset-inline-start: auto !important;
            inset-inline-end: 16px !important;
        }

        .map-page .map-legend {
            inset-inline-end: 16px !important;
        }

        @media (max-width: 1023px) {
            .map-page .main-content {
                top: 4rem;
                right: 0;
                left: 0;
                bottom: 0;
                width: auto;
                height: auto;
            }

            .map-page .map-shell {
                height: 100% !important;
                min-height: 0 !important;
            }

            .map-page #map {
                height: 100% !important;
                min-height: 0 !important;
            }

            .map-page .map-tool-dock {
                inset-inline: 10px !important;
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
