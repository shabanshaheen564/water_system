<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'نظام إدارة المياه ونظم المعلومات الجغرافية - بلدية دير البلح') }} - {{ $title ?? __('Dashboard') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="min-h-screen bg-slate-50 dark:bg-slate-900">
    <div id="sidebar-overlay" class="fixed inset-0 z-40 hidden bg-black/50 lg:hidden" aria-hidden="true"></div>

    <aside id="sidebar" class="fixed inset-y-0 right-0 z-50 flex w-64 -translate-x-0 translate-x-full flex-col border-l border-slate-200 bg-white transition-transform duration-300 ease-in-out dark:border-slate-700 dark:bg-slate-800 lg:translate-x-0" role="navigation" aria-label="Main navigation">
        <div class="flex h-16 shrink-0 items-center justify-between border-b border-slate-200 px-4 dark:border-slate-700">
            <a href="{{ route('gis.index') }}" class="flex min-w-0 items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-700 text-white shadow-lg shadow-red-900/20">
                    <svg class="h-6 w-6" viewBox="0 0 64 64" fill="none" aria-hidden="true"><path d="M8 48h48M14 45V25l12-8 12 8v20M43 45V28l8-5 5 4v18M20 45V32h6v13M31 45V32h6v13" stroke="currentColor" stroke-width="4" stroke-linejoin="round"/></svg>
                </div>
                <span class="truncate text-sm font-bold text-slate-900 dark:text-white">بلدية دير البلح</span>
            </a>
            <button id="sidebar-close" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden dark:hover:bg-slate-700" aria-label="إغلاق القائمة"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>

        <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto p-4" aria-label="Sidebar navigation">
            <a href="{{ route('gis.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium {{ request()->routeIs('gis.index') ? 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400' : 'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700' }}"><span>▦</span><span>لوحة التحكم</span></a>
            <a href="{{ route('datasets.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium {{ request()->routeIs('datasets.index') || request()->routeIs('datasets.show') || request()->routeIs('datasets.records.index') ? 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400' : 'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700' }}"><span>▤</span><span>قواعد البيانات</span></a>
            @can('datasets.create')
                <a href="{{ route('datasets.create') }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium {{ request()->routeIs('datasets.create') ? 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400' : 'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700' }}"><span>＋</span><span>إضافة مجموعة بيانات</span></a>
            @endcan
            @if ($spatialDatasetsCount > 0)
                <a href="{{ route('map.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium {{ request()->routeIs('map.index') ? 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400' : 'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700' }}"><span>⌖</span><span>الخريطة الجغرافية</span></a>
            @endif
            @can('users.view')
                <a href="{{ route('users.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium {{ request()->routeIs('users.index') ? 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400' : 'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700' }}"><span>♙</span><span>المستخدمون</span></a>
            @endcan
            @can('roles.view')
                <a href="{{ route('roles.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium {{ request()->routeIs('roles.index') ? 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400' : 'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700' }}"><span>◈</span><span>الأدوار</span></a>
            @endcan
            @can('permissions.view')
                <a href="{{ route('permissions.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium {{ request()->routeIs('permissions.index') ? 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400' : 'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700' }}"><span>✓</span><span>الصلاحيات</span></a>
            @endcan
        </nav>

        <div class="shrink-0 border-t border-slate-200 p-4 dark:border-slate-700">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-red-100 text-sm font-bold text-red-700 dark:bg-red-900/40 dark:text-red-300">{{ Str::upper(mb_substr(auth()->user()->name, 0, 1)) }}</div>
                <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ auth()->user()->name }}</p><p class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</p></div>
            </div>
        </div>
    </aside>

    <!-- Explicit right margin keeps the header/content completely clear of the sidebar. -->
    <div class="min-h-screen lg:mr-64">
        <header class="sticky top-0 z-30 h-16 border-b border-slate-200 bg-white/95 backdrop-blur dark:border-slate-700 dark:bg-slate-800/95">
            <div class="flex h-full items-center justify-between px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-3">
                    <button id="sidebar-toggle" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden dark:hover:bg-slate-700" aria-label="فتح القائمة" aria-expanded="false" aria-controls="sidebar"><svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg></button>
                    <div><p class="text-xs font-semibold text-red-700">بلدية دير البلح</p><h1 class="text-lg font-bold text-slate-900 dark:text-white">{{ $title ?? __('Dashboard') }}</h1></div>
                </div>
                @auth
                    <div id="user-menu-container" class="relative">
                        <button id="user-menu-toggle" class="flex items-center gap-2 rounded-lg p-1.5 hover:bg-slate-100 dark:hover:bg-slate-700" aria-expanded="false" aria-haspopup="true"><div class="flex h-8 w-8 items-center justify-center rounded-full bg-red-100 text-sm font-bold text-red-700 dark:bg-red-900/40 dark:text-red-300">{{ Str::upper(mb_substr(auth()->user()->name, 0, 1)) }}</div><span class="hidden text-sm font-semibold text-slate-700 sm:block dark:text-slate-300">{{ auth()->user()->name }}</span><span class="text-slate-400">⌄</span></button>
                        <div id="user-menu" class="absolute left-0 top-full z-50 mt-2 hidden w-64 rounded-xl border border-slate-200 bg-white py-2 shadow-xl dark:border-slate-700 dark:bg-slate-800">
                            <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-700"><p class="text-sm font-semibold text-slate-900 dark:text-white">{{ auth()->user()->name }}</p><p class="mt-1 text-xs text-slate-500">{{ auth()->user()->email }}</p>@if(auth()->user()->roles->count())<p class="mt-2 text-xs font-semibold text-red-700 dark:text-red-400">{{ auth()->user()->roles->pluck('name')->implode(', ') }}</p>@endif</div>
                            <form method="POST" action="{{ route('logout') }}" class="p-2">@csrf<button type="submit" class="w-full rounded-lg px-3 py-2 text-right text-sm font-semibold text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20">تسجيل الخروج</button></form>
                        </div>
                    </div>
                @endauth
            </div>
        </header>

        <main class="min-w-0">
            @if (session('success'))<div class="mx-auto mt-4 max-w-7xl px-4 sm:px-6 lg:px-8"><div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-800" role="alert">{{ session('success') }}</div></div>@endif
            @if (session('error'))<div class="mx-auto mt-4 max-w-7xl px-4 sm:px-6 lg:px-8"><div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800" role="alert">{{ session('error') }}</div></div>@endif
            @yield('content')
        </main>

        <footer class="border-t border-slate-200 bg-white py-4 text-center text-xs text-slate-500 dark:border-slate-700 dark:bg-slate-800">بلدية دير البلح — دائرة المياه والصرف الصحي © {{ date('Y') }}</footer>
    </div>

    @vite('resources/js/app.js')
    @stack('scripts')
</body>
</html>
