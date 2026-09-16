@extends('layouts.guest')

@section('content')
<div class="mx-auto flex min-h-[calc(100vh-57px)] max-w-6xl items-center px-4 py-12 sm:px-6 lg:px-8">
    <div class="w-full border border-border bg-white">
        <div class="h-1 bg-brand-600"></div>
        <div class="grid lg:grid-cols-[1.35fr_.65fr]">
            <section class="p-8 sm:p-12 lg:p-16">
                <div class="mb-10 flex items-center gap-4">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-md bg-brand-600 text-white" aria-hidden="true">
                        <svg class="h-10 w-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 20h18M5 20V9l7-5 7 5v11M9 20v-6h6v6M17 8V5l3 2v13" stroke-linejoin="round"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-brand-600">{{ __('messages.public.state') }}</p>
                        <h1 class="text-2xl font-semibold leading-[1.5] text-ink">{{ __('messages.app.municipality') }}</h1>
                    </div>
                </div>

                <p class="mb-2 text-sm font-medium text-ink-secondary">{{ __('messages.app.department') }} — {{ __('messages.app.section') }}</p>
                <h2 class="max-w-3xl text-3xl font-semibold leading-[1.5] text-ink sm:text-4xl">{{ __('messages.public.title') }}</h2>
                <p class="mt-5 max-w-2xl text-base leading-[1.9] text-ink-secondary">{{ __('messages.public.description') }}</p>

                <div class="mt-9 grid gap-3 sm:grid-cols-3">
                    <div class="border border-border bg-surface-1 p-4">
                        <p class="font-semibold text-ink">{{ __('messages.public.central_data') }}</p>
                        <p class="mt-1 text-xs text-ink-secondary">{{ __('messages.public.central_data_desc') }}</p>
                    </div>
                    <div class="border border-border bg-surface-1 p-4">
                        <p class="font-semibold text-ink">{{ __('messages.public.gis') }}</p>
                        <p class="mt-1 text-xs text-ink-secondary">{{ __('messages.public.gis_desc') }}</p>
                    </div>
                    <div class="border border-border bg-surface-1 p-4">
                        <p class="font-semibold text-ink">{{ __('messages.public.water_assets') }}</p>
                        <p class="mt-1 text-xs text-ink-secondary">{{ __('messages.public.water_assets_desc') }}</p>
                    </div>
                </div>

                <a href="{{ route('login') }}" class="mt-9 inline-flex items-center gap-2 rounded-md bg-brand-600 px-6 py-3 text-sm font-medium text-white hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-100">
                    {{ __('messages.public.login') }}
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
            </section>

            <aside class="border-t border-border bg-surface-1 p-8 sm:p-10 lg:border-s lg:border-t-0">
                <p class="text-xs font-medium text-brand-600">{{ __('messages.public.portal') }}</p>
                <h3 class="mt-5 text-2xl font-semibold leading-[1.6] text-ink">{{ __('messages.public.title') }}</h3>
                <div class="mt-8 space-y-3">
                    <div class="border border-border bg-white p-4">
                        <p class="text-sm font-medium text-ink">{{ __('messages.public.gis') }}</p>
                        <p class="mt-1 text-xs text-ink-secondary">{{ __('messages.public.gis_desc') }}</p>
                    </div>
                    <div class="border border-border bg-white p-4">
                        <p class="text-sm font-medium text-ink">{{ __('messages.navigation.datasets') }}</p>
                        <p class="mt-1 text-xs text-ink-secondary">{{ __('messages.public.central_data_desc') }}</p>
                    </div>
                    <div class="border border-border bg-white p-4">
                        <p class="text-sm font-medium text-ink">{{ __('messages.public.water_assets') }}</p>
                        <p class="mt-1 text-xs text-ink-secondary">{{ __('messages.public.water_assets_desc') }}</p>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</div>
@endsection
