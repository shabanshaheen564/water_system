<header class="fixed inset-x-0 top-0 z-[1100] h-16 border-b border-border bg-white">
    <div class="flex h-full items-center justify-between px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-3">
            <button id="sidebar-toggle" class="rounded-md p-2 text-ink-secondary hover:bg-surface-1 lg:hidden" aria-label="{{ __('messages.ui.open_menu') }}" aria-expanded="false" aria-controls="sidebar">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <div>
                <p class="text-xs font-medium text-brand-600">{{ __('messages.app.municipality') }}</p>
                <h1 class="text-xl font-semibold leading-[1.5] text-ink">{{ $title ?? __('messages.navigation.dashboard') }}</h1>
            </div>
        </div>
        @auth
        <div id="user-menu-container" class="relative">
            <button id="user-menu-toggle" class="btn-motion flex items-center gap-2 rounded-md p-1.5 hover:bg-surface-1" aria-expanded="false" aria-haspopup="true">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-50 text-sm font-semibold text-brand-600">{{ Str::upper(mb_substr(auth()->user()->name, 0, 1)) }}</div>
                <span class="hidden text-sm font-medium text-ink-secondary sm:block">{{ auth()->user()->name }}</span>
                <svg id="user-menu-chevron" class="h-4 w-4 text-ink-muted transition-transform duration-[220ms]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" d="m7 10 5 5 5-5"/></svg>
            </button>
            <div id="user-menu" class="absolute end-0 top-full z-50 mt-2 hidden w-64 rounded-md border border-border bg-white py-2 shadow-md">
                <div class="border-b border-border px-4 py-3">
                    <p class="text-sm font-semibold text-ink">{{ auth()->user()->name }}</p>
                    <p class="mt-1 text-xs text-ink-muted ltr-value">{{ auth()->user()->email }}</p>
                    @if(auth()->user()->roles->count())<p class="mt-2 text-xs font-medium text-brand-600">{{ auth()->user()->roles->map(fn ($role) => __('messages.roles.' . $role->name))->implode('، ') }}</p>@endif
                </div>
                <form method="POST" action="{{ route('logout') }}" class="p-2">
                    @csrf
                    <button type="submit" class="btn-motion flex w-full items-center gap-2 rounded-md px-3 py-2 text-start text-sm font-medium text-danger hover:bg-danger-surface">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" d="M10 17l5-5-5-5M15 12H3M21 3v18"/></svg>
                        {{ __('messages.navigation.logout') }}
                    </button>
                </form>
            </div>
        </div>
        @endauth
    </div>
</header>