<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ config('app.direction', 'rtl') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'نظام إدارة بيانات المياه ونظم المعلومات الجغرافية') }} - {{ $title ?? __('Dashboard') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="bg-slate-50 dark:bg-slate-900 min-h-screen">
    <div id="sidebar-overlay" class="fixed inset-0 z-40 bg-black/50 hidden lg:hidden" aria-hidden="true"></div>

    <aside id="sidebar" class="fixed inset-y-0 start-0 z-50 w-64 bg-white dark:bg-slate-800 border-e border-gray-200 dark:border-slate-700 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out flex flex-col" role="navigation" aria-label="Main navigation">
        <div class="flex items-center justify-between h-16 px-4 border-b border-gray-200 dark:border-slate-700 shrink-0">
            <a href="{{ route('gis.index') }}" class="flex items-center gap-3 min-w-0" aria-label="{{ config('app.name') }}">
                <div class="h-10 w-10 shrink-0 bg-blue-600 rounded-lg flex items-center justify-center shadow-lg shadow-blue-500/25">
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z" /></svg>
                </div>
                <span class="text-lg font-bold text-gray-900 dark:text-white truncate">{{ config('app.name', 'Water GIS') }}</span>
            </a>
            <button id="sidebar-close" class="lg:hidden p-2 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700" aria-label="{{ __('Close sidebar') }}"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
        </div>

        <nav class="flex-1 min-h-0 p-4 space-y-1 overflow-y-auto" aria-label="Sidebar navigation">
            <a href="{{ route('gis.index') }}" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('gis.index') ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700' }}"><svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg><span>{{ __('Dashboard') }}</span></a>

            <a href="{{ route('datasets.index') }}" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('datasets.index') || request()->routeIs('datasets.show') || request()->routeIs('datasets.records.index') ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700' }}"><svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V21a2 2 0 01-2 2h-6" /></svg><span>{{ __('Datasets') }}</span></a>

            @can('datasets.create')
            <a href="{{ route('datasets.create') }}" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('datasets.create') ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700' }}"><svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg><span>{{ __('Create Dataset') }}</span></a>
            @endcan

            @if ($spatialDatasetsCount > 0)
            <a href="{{ route('map.index') }}" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('map.index') ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700' }}"><svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z" /></svg><span>{{ __('GIS Map') }}</span></a>
            @endif

            @can('users.view')
            <a href="{{ route('users.index') }}" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('users.index') ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700' }}"><svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg><span>{{ __('Users') }}</span></a>
            @endcan

            @can('roles.view')
            <a href="{{ route('roles.index') }}" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('roles.index') ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700' }}"><svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg><span>{{ __('Roles') }}</span></a>
            @endcan

            @can('permissions.view')
            <a href="{{ route('permissions.index') }}" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('permissions.index') ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700' }}"><svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg><span>{{ __('Permissions') }}</span></a>
            @endcan
        </nav>

        <div class="p-4 border-t border-gray-200 dark:border-slate-700 shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                <div class="h-8 w-8 shrink-0 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center"><span class="text-sm font-medium text-blue-600 dark:text-blue-400">{{ Str::upper(auth()->user()->name[0] ?? 'U') }}</span></div>
                <div class="flex-1 min-w-0"><p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ auth()->user()->name }}</p><p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ auth()->user()->email }}</p></div>
            </div>
        </div>
    </aside>

    <div class="min-h-screen lg:ms-64 flex flex-col">
        <header class="sticky top-0 z-30 h-16 shrink-0 bg-white/90 dark:bg-slate-800/90 backdrop-blur-sm border-b border-gray-200 dark:border-slate-700">
            <div class="h-full px-4 sm:px-6 lg:px-8 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <button id="sidebar-toggle" class="lg:hidden p-2 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700" aria-label="{{ __('Open sidebar') }}" aria-expanded="false" aria-controls="sidebar"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg></button>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">{{ $title ?? __('Dashboard') }}</h1>
                </div>

                @auth
                <div class="relative" id="user-menu-container">
                    <button id="user-menu-toggle" class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500" aria-label="{{ __('User menu') }}" aria-expanded="false" aria-haspopup="true">
                        <div class="h-8 w-8 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center"><span class="text-sm font-medium text-blue-600 dark:text-blue-400">{{ Str::upper(auth()->user()->name[0] ?? 'U') }}</span></div>
                        <span class="hidden sm:block text-sm font-medium text-gray-700 dark:text-gray-300">{{ auth()->user()->name }}</span>
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div id="user-menu" class="absolute end-0 top-full mt-2 w-56 bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-gray-200 dark:border-slate-700 py-1 hidden z-50" role="menu" aria-orientation="vertical">
                        <div class="px-4 py-2 border-b border-gray-200 dark:border-slate-700"><p class="text-sm font-medium text-gray-900 dark:text-white">{{ auth()->user()->name }}</p><p class="text-xs text-gray-500 dark:text-gray-400">{{ auth()->user()->email }}</p>@if(auth()->user()->roles->count())<p class="text-xs text-blue-600 dark:text-blue-400 mt-1">{{ auth()->user()->roles->pluck('name')->implode(', ') }}</p>@endif</div>
                        <form method="POST" action="{{ route('logout') }}" class="p-1">@csrf<button type="submit" class="w-full text-right px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-slate-700 rounded transition-colors" role="menuitem">{{ __('Logout') }}</button></form>
                    </div>
                </div>
                @endauth
            </div>
        </header>

        <main class="flex-1 min-w-0">
            @if (session('success'))
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4"><div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-400 px-4 py-3 rounded-lg" role="alert">{{ session('success') }}</div></div>
            @endif
            @if (session('error'))
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4"><div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-400 px-4 py-3 rounded-lg" role="alert">{{ session('error') }}</div></div>
            @endif
            @yield('content')
        </main>

        <footer class="bg-white dark:bg-slate-800 border-t border-gray-200 dark:border-slate-700 py-4 shrink-0"><div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-sm text-gray-500 dark:text-gray-400">{{ config('app.name', 'Water GIS Management System') }} &copy; {{ date('Y') }}</div></footer>
    </div>

    @vite('resources/js/app.js')
    @stack('scripts')
</body>
</html>
