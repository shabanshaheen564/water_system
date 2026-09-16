@extends('layouts.guest')

@section('content')
<div class="mx-auto flex min-h-[calc(100vh-57px)] max-w-md items-center px-4 py-12">
    <div class="w-full">
        <div class="mb-7 text-center">
            <a href="{{ route('home') }}" class="inline-flex items-center justify-center">
                <div class="flex h-16 w-16 items-center justify-center rounded-md bg-brand-600 text-white" aria-hidden="true">
                    <svg class="h-10 w-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 20h18M5 20V9l7-5 7 5v11M9 20v-6h6v6M17 8V5l3 2v13" stroke-linejoin="round"/></svg>
                </div>
            </a>
            <p class="mt-5 text-sm font-medium text-brand-600">{{ __('messages.app.municipality') }}</p>
            <h1 class="mt-1 text-2xl font-semibold leading-[1.5] text-ink">{{ __('messages.public.login') }}</h1>
            <p class="mt-1 text-sm text-ink-secondary">{{ __('messages.public.title') }}</p>
        </div>

        <div class="border border-border bg-white p-6 sm:p-8">
            <form id="login-form" method="POST" action="{{ route('login') }}" class="space-y-5">
                @csrf
                <div>
                    <label for="email" class="mb-2 block text-sm font-medium text-ink">{{ __('messages.public.email') }}</label>
                    <input id="email" name="email" type="email" autocomplete="email" required value="{{ old('email') }}" class="input-institutional block w-full px-4 py-3 text-ink outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100" placeholder="{{ __('messages.public.email_placeholder') }}">
                    @error('email')<p class="mt-2 text-sm text-danger" role="alert">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="password" class="mb-2 block text-sm font-medium text-ink">{{ __('messages.public.password') }}</label>
                    <div class="relative">
                        <input id="password" name="password" type="password" autocomplete="current-password" required class="input-institutional block w-full px-4 py-3 pe-12 text-ink outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100" placeholder="{{ __('messages.public.password_placeholder') }}">
                        <button type="button" id="toggle-password" class="absolute inset-y-0 end-0 px-4 text-ink-muted hover:text-brand-600" aria-label="{{ __('messages.public.show_password') }}" aria-pressed="false">
                            <svg id="eye-open" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                            <svg id="eye-closed" class="hidden h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="m4 4 16 16M10.6 10.6a2 2 0 0 0 2.8 2.8M8.5 5.8A10.6 10.6 0 0 1 12 5c6.5 0 10 7 10 7a19.6 19.6 0 0 1-3.4 4.2M6.1 6.1C3.3 8.3 2 12 2 12s3.5 7 10 7c1.3 0 2.5-.3 3.6-.7"/></svg>
                        </button>
                    </div>
                    @error('password')<p class="mt-2 text-sm text-danger" role="alert">{{ $message }}</p>@enderror
                </div>

                @if(session('error'))
                    <div class="border border-red-200 bg-danger-surface p-3 text-sm text-danger" role="alert">{{ session('error') }}</div>
                @endif

                <button id="login-button" type="submit" class="w-full rounded-md bg-brand-600 px-4 py-3 text-sm font-medium text-white hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-100 disabled:cursor-not-allowed disabled:opacity-60">
                    <span id="button-text">{{ __('messages.public.login_short') }}</span>
                    <span id="button-spinner" class="hidden">{{ __('messages.public.logging_in') }}</span>
                </button>
            </form>

            <div class="mt-6 border-t border-border pt-5 text-center text-xs text-ink-muted">
                {{ __('messages.app.department') }} — {{ __('messages.app.municipality') }}
            </div>
        </div>

        <div class="mt-5 text-center"><a href="{{ route('home') }}" class="text-sm font-medium text-ink-secondary hover:text-brand-600">{{ __('messages.public.back_home') }}</a></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    window.initLoginPage?.();
</script>
@endpush
