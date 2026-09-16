<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? __('messages.app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white text-ink">
    <main class="min-h-screen">
        @yield('content')
    </main>
    <footer class="border-t border-border bg-white py-4 text-center text-xs text-ink-muted">
        {{ __('messages.app.municipality') }} — {{ __('messages.app.department') }} © {{ date('Y') }}
    </footer>
</body>
</html>
