<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ config('app.direction', 'ltr') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Water GIS Management System') }} - {{ $title ?? __('GIS Map') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">
    <header class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center">
                    <a href="{{ route('home') }}" class="flex items-center space-x-3">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <span class="text-xl font-bold text-gray-900">{{ config('app.name', 'Water GIS Management System') }}</span>
                    </a>
                </div>

                <nav class="hidden md:flex items-center space-x-6" role="navigation" aria-label="Main navigation">
                    <a href="{{ route('gis.index') }}" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium transition-colors {{ request()->routeIs('gis.index') ? 'bg-blue-50 text-blue-600' : '' }}">
                        {{ __('GIS Map') }}
                    </a>
                    <a href="{{ route('datasets.index') }}" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium transition-colors {{ request()->routeIs('datasets.index') ? 'bg-blue-50 text-blue-600' : '' }}">
                        {{ __('Datasets') }}
                    </a>
                </nav>

                <div class="flex items-center space-x-4">
                    @auth
                        <div class="flex items-center space-x-3">
                            <span class="text-sm text-gray-700">{{ auth()->user()->name }}</span>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="text-sm text-gray-700 hover:text-blue-600 transition-colors">
                                    {{ __('Logout') }}
                                </button>
                            </form>
                        </div>
                    @endauth
                </div>
            </div>
        </div>
    </header>

    <main class="flex-1 flex">
        <div class="w-full lg:w-3/4">
            @if (session('success'))
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
                    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg" role="alert">
                        {{ session('success') }}
                    </div>
                </div>
            @endif

            @if (session('error'))
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
                    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg" role="alert">
                        {{ session('error') }}
                    </div>
                </div>
            @endif

            @yield('content')
        </div>

        <aside class="hidden lg:block lg:w-1/4 bg-white border-l border-gray-200">
            @yield('sidebar')
        </aside>
    </main>

    <footer class="bg-white border-t border-gray-200 py-4">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-sm text-gray-500">
            {{ config('app.name', 'Water GIS Management System') }} - {{ __('نظام إدارة بيانات المياه ونظم المعلومات الجغرافية') }}
        </div>
    </footer>

    @vite('resources/js/app.js')
    @stack('scripts')
</body>
</html>