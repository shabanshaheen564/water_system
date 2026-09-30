<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} — {{ $title ?? __('messages.navigation.dashboard') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .map-page { overflow: hidden; }

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

        .map-page footer { display: none; }

        /* RTL application shell: sidebar is right, map tools remain on the map. */
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

        /* Leaflet zoom: right side of the map, immediately beside the RTL sidebar.
           The map itself already starts below the fixed header, so do not use a
           viewport-fixed position here. */
        .map-page .map-shell .leaflet-control-zoom {
            position: absolute !important;
            top: 18px !important;
            right: 18px !important;
            left: auto !important;
            bottom: auto !important;
            margin: 0 !important;
            z-index: 1300 !important;
            direction: ltr !important;
            pointer-events: auto !important;
        }

        .map-page .map-shell .leaflet-control-zoom a {
            direction: ltr !important;
            text-align: center !important;
        }

        .map-page .map-shell .leaflet-control-attribution {
            position: absolute !important;
            left: 50% !important;
            right: auto !important;
            bottom: 0 !important;
            margin: 0 !important;
            transform: translateX(-50%) !important;
            white-space: nowrap;
            z-index: 1300 !important;
        }

        /* Keep the map usable with a hand cursor. */
        .map-page #map,
        .map-page #map.leaflet-container,
        .map-page #map.leaflet-container.leaflet-grab { cursor: grab !important; }
        .map-page #map.leaflet-container.leaflet-dragging,
        .map-page #map.leaflet-container.leaflet-dragging .leaflet-grab { cursor: grabbing !important; }

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

            .map-page .map-shell .leaflet-control-zoom {
                top: 12px !important;
                right: 12px !important;
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

    @if(request()->routeIs('map.*'))
    <script>
        /* Map-only UI hardening. This does not replace the existing GIS logic;
           it only keeps the native Leaflet zoom visible and repairs the small
           operational counters from the rendered map state. */
        document.addEventListener('DOMContentLoaded', () => {
            const syncMapCounters = () => {
                const complaints = document.querySelectorAll('#map .map-marker.complaint').length;
                const tasks = document.querySelectorAll('#map .map-marker.task').length;
                const datasets = document.querySelectorAll('#dataset-layers .dataset-toggle').length;

                const setCounter = (id, value) => {
                    const element = document.getElementById(id);
                    if (element) element.textContent = String(value);
                };

                /* Do not overwrite a server/JS value with zero while the map is
                   still loading. Once markers exist, the rendered count is exact. */
                if (complaints > 0) setCounter('stat-complaints', complaints);
                if (tasks > 0) setCounter('stat-tasks', tasks);
                if (datasets > 0) setCounter('stat-datasets', datasets);
            };

            const keepZoomBesideSidebar = () => {
                const shell = document.querySelector('.map-page .map-shell');
                const zoom = shell?.querySelector('.leaflet-control-zoom');
                if (!zoom) return;
                zoom.style.setProperty('top', '18px', 'important');
                zoom.style.setProperty('right', '18px', 'important');
                zoom.style.setProperty('left', 'auto', 'important');
                zoom.style.setProperty('bottom', 'auto', 'important');
                zoom.style.setProperty('margin', '0', 'important');
            };

            const observeMap = () => {
                const map = document.getElementById('map');
                if (!map) return;
                const observer = new MutationObserver(() => {
                    keepZoomBesideSidebar();
                    syncMapCounters();
                });
                observer.observe(map, { childList: true, subtree: true });
                syncMapCounters();
                keepZoomBesideSidebar();
            };

            window.setTimeout(observeMap, 100);
            window.setTimeout(() => { syncMapCounters(); keepZoomBesideSidebar(); }, 700);
            window.setTimeout(() => { syncMapCounters(); keepZoomBesideSidebar(); }, 1800);
        });
    </script>
    @endif
</body>
</html>
