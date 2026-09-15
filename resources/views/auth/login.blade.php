<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ config('app.direction', 'rtl') }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'نظام إدارة بيانات المياه ونظم المعلومات الجغرافية') }} - {{ __('Login') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-slate-50 dark:bg-slate-900 min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div class="text-center">
            <div class="mx-auto h-16 w-16 bg-blue-600 rounded-full flex items-center justify-center shadow-lg shadow-blue-500/25">
                <svg class="h-10 w-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z" />
                </svg>
            </div>
            <h2 class="mt-6 text-2xl font-bold text-gray-900 dark:text-white">
                {{ config('app.name', 'نظام إدارة بيانات المياه') }}
            </h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                {{ __('نظام إدارة بيانات المياه ونظم المعلومات الجغرافية') }}
            </p>
        </div>

        <form id="login-form" class="mt-8 space-y-6" method="POST" action="{{ route('login') }}">
            @csrf

            <div class="space-y-4">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        {{ __('Email address') }}
                    </label>
                    <div class="relative">
                        <input
                            id="email"
                            name="email"
                            type="email"
                            autocomplete="email"
                            required
                            class="appearance-none relative block w-full px-4 py-3 border border-gray-300 dark:border-gray-600 placeholder-gray-400 dark:placeholder-gray-500 text-gray-900 dark:text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-white dark:bg-gray-800"
                            placeholder="{{ __('Email address') }}"
                            value="{{ old('email') }}"
                            aria-describedby="email-error">
                        <div class="absolute inset-y-0 end-0 flex items-center pe-3 pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 002 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                    </div>
                    @error('email')
                    <p id="email-error" class="mt-1 text-sm text-red-600 dark:text-red-400" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        {{ __('Password') }}
                    </label>
                    <div class="relative">
                        <input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            required
                            class="appearance-none relative block w-full px-4 py-3 border border-gray-300 dark:border-gray-600 placeholder-gray-400 dark:placeholder-gray-500 text-gray-900 dark:text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-white dark:bg-gray-800 pr-12"
                            placeholder="{{ __('Password') }}"
                            aria-describedby="password-error">
                        <button
                            type="button"
                            id="toggle-password"
                            class="absolute inset-y-0 end-0 flex items-center pe-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-r-lg transition-colors"
                            aria-label="{{ __('Show password') }}"
                            aria-pressed="false">
                            <svg id="eye-open" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <svg id="eye-closed" class="h-5 w-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88a3 3 0 114.242 4.25M12 6c2.146 0 4.108.794 5.646 2.155" />
                            </svg>
                        </button>
                    </div>
                    @error('password')
                    <p id="password-error" class="mt-1 text-sm text-red-600 dark:text-red-400" role="alert">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div id="api-error" class="hidden p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 text-sm" role="alert" aria-live="polite"></div>

            <div>
                <button
                    type="submit"
                    id="login-button"
                    class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 shadow-sm hover:shadow-md">
                    <span id="button-text">{{ __('Sign in') }}</span>
                    <svg id="button-spinner" class="hidden animate-spin -ml-1 mr-2 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                    </svg>
                </button>
            </div>
        </form>

        <div class="text-center text-sm text-gray-500 dark:text-gray-400">
            <p>{{ __('نظام إدارة بيانات المياه ونظم المعلومات الجغرافية') }}</p>
            <p class="mt-1">{{ config('app.name', 'Water GIS Management System') }} &copy; {{ date('Y') }}</p>
        </div>
    </div>

    <script>
        (function() {
            'use strict';

            const form = document.getElementById('login-form');
            const passwordInput = document.getElementById('password');
            const togglePasswordBtn = document.getElementById('toggle-password');
            const eyeOpen = document.getElementById('eye-open');
            const eyeClosed = document.getElementById('eye-closed');
            const loginButton = document.getElementById('login-button');
            const buttonText = document.getElementById('button-text');
            const buttonSpinner = document.getElementById('button-spinner');

            // Show/hide password toggle
            togglePasswordBtn.addEventListener('click', function() {
                const isPassword = passwordInput.type === 'password';
                passwordInput.type = isPassword ? 'text' : 'password';
                eyeOpen.classList.toggle('hidden', isPassword);
                eyeClosed.classList.toggle('hidden', !isPassword);
                togglePasswordBtn.setAttribute('aria-label', isPassword ? '{{ __('
                    Hide password ') }}' : '{{ __('
                    Show password ') }}');
                togglePasswordBtn.setAttribute('aria-pressed', isPassword);
            });

            // Form submission - show loading state, then let browser handle the rest
            form.addEventListener('submit', function(e) {
                // Client-side validation
                const emailInput = document.getElementById('email');
                if (!emailInput.value.trim()) {
                    e.preventDefault();
                    emailInput.focus();
                    return;
                }
                if (!passwordInput.value) {
                    e.preventDefault();
                    passwordInput.focus();
                    return;
                }

                // Show loading state
                loginButton.disabled = true;
                buttonText.textContent = '{{ __('
                Signing in ...') }}';
                buttonSpinner.classList.remove('hidden');

                // Let the form submit normally to /login (web endpoint)
                // which uses webLogin() -> Auth::login() -> session -> redirect to /gis (Dashboard)
            });
        })();
    </script>
</body>

</html>