<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>تسجيل الدخول - نظام إدارة المياه ونظم المعلومات الجغرافية</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">
        <div class="mb-6 text-center">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-3 group">
                <div class="h-16 w-16 rounded-2xl bg-red-700 flex items-center justify-center shadow-lg shadow-red-900/20 group-hover:bg-red-800 transition">
                    <svg class="h-10 w-10 text-white" viewBox="0 0 64 64" fill="none" aria-hidden="true">
                        <path d="M8 48h48" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
                        <path d="M14 45V25l12-8 12 8v20" stroke="currentColor" stroke-width="4" stroke-linejoin="round"/>
                        <path d="M43 45V28l8-5 5 4v18" stroke="currentColor" stroke-width="4" stroke-linejoin="round"/>
                        <path d="M20 45V32h6v13M31 45V32h6v13" stroke="currentColor" stroke-width="3"/>
                    </svg>
                </div>
            </a>
            <p class="mt-5 text-sm font-semibold text-red-700">بلدية دير البلح</p>
            <h1 class="mt-1 text-2xl font-extrabold text-slate-900">تسجيل الدخول إلى النظام</h1>
            <p class="mt-2 text-sm text-slate-500">نظام إدارة المياه ونظم المعلومات الجغرافية</p>
        </div>

        <div class="rounded-2xl bg-white border border-slate-200 shadow-xl p-6 sm:p-8">
            <form id="login-form" method="POST" action="{{ route('login') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-semibold text-slate-700 mb-2">البريد الإلكتروني</label>
                    <input id="email" name="email" type="email" autocomplete="email" required value="{{ old('email') }}"
                        class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-red-600 focus:ring-4 focus:ring-red-100"
                        placeholder="أدخل البريد الإلكتروني">
                    @error('email')
                        <p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-semibold text-slate-700 mb-2">كلمة المرور</label>
                    <div class="relative">
                        <input id="password" name="password" type="password" autocomplete="current-password" required
                            class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 pe-12 text-slate-900 outline-none transition focus:border-red-600 focus:ring-4 focus:ring-red-100"
                            placeholder="أدخل كلمة المرور">
                        <button type="button" id="toggle-password" class="absolute inset-y-0 end-0 px-4 text-slate-400 hover:text-red-700 focus:outline-none" aria-label="إظهار كلمة المرور" aria-pressed="false">
                            <svg id="eye-open" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <svg id="eye-closed" class="hidden h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18M10.584 10.587a2 2 0 002.829 2.828M9.88 4.24A9.987 9.987 0 0112 4c4.478 0 8.268 2.943 9.542 7a9.953 9.953 0 01-3.214 4.55M6.228 6.228A9.953 9.953 0 002.458 12C3.732 16.057 7.523 19 12 19a9.987 9.987 0 004.12-.885"/></svg>
                        </button>
                    </div>
                    @error('password')
                        <p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div id="api-error" class="hidden rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700" role="alert"></div>

                <button id="login-button" type="submit" class="w-full rounded-xl bg-red-700 px-4 py-3.5 text-sm font-bold text-white shadow-lg shadow-red-900/20 hover:bg-red-800 focus:outline-none focus:ring-4 focus:ring-red-200 disabled:cursor-not-allowed disabled:opacity-60 transition">
                    <span id="button-text">دخول النظام</span>
                    <span id="button-spinner" class="hidden"> جارٍ تسجيل الدخول...</span>
                </button>
            </form>

            <div class="mt-6 border-t border-slate-100 pt-5 text-center text-xs text-slate-500">
                <p>دائرة المياه والصرف الصحي</p>
                <p class="mt-1">بلدية دير البلح © {{ date('Y') }}</p>
            </div>
        </div>

        <div class="mt-5 text-center">
            <a href="{{ route('home') }}" class="text-sm font-semibold text-slate-500 hover:text-red-700">العودة إلى الصفحة الرئيسية</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('login-form');
            const password = document.getElementById('password');
            const toggle = document.getElementById('toggle-password');
            const openIcon = document.getElementById('eye-open');
            const closedIcon = document.getElementById('eye-closed');
            const button = document.getElementById('login-button');
            const text = document.getElementById('button-text');
            const spinner = document.getElementById('button-spinner');

            toggle.addEventListener('click', function () {
                const showing = password.type === 'password';
                password.type = showing ? 'text' : 'password';
                openIcon.classList.toggle('hidden', showing);
                closedIcon.classList.toggle('hidden', !showing);
                toggle.setAttribute('aria-pressed', showing ? 'true' : 'false');
                toggle.setAttribute('aria-label', showing ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور');
            });

            form.addEventListener('submit', function () {
                button.disabled = true;
                text.classList.add('hidden');
                spinner.classList.remove('hidden');
            });
        });
    </script>
</body>
</html>
