@extends('layouts.app')

@section('title', __('Roles'))

@section('content')
    <div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
        <div class="mb-6 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold leading-[1.5] text-ink">{{ __('Roles') }}</h2>
                <p class="mt-1 text-sm text-ink-secondary">{{ __('View system roles and their current usage') }}</p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @forelse($roles as $role)
                <div class="card-institutional p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-ink">{{ __('messages.roles.' . $role->name) }}</h3>
                            <p class="mt-1 text-xs text-ink-muted ltr-value">{{ $role->guard_name }}</p>
                        </div>
                        <span class="inline-flex rounded-md border border-border bg-surface-1 px-2 py-1 text-xs font-medium text-ink-secondary">
                            {{ $role->permissions_count }} {{ __('permissions') }}
                        </span>
                    </div>
                    <div class="mt-5 flex items-center justify-between gap-3 border-t border-border pt-4">
                        <span class="text-sm text-ink-secondary">{{ $role->users_count }} {{ __('assigned users') }}</span>
                        @can('roles.update')
                            @if($role->name !== 'System Owner')
                                <a href="{{ route('roles.edit', $role) }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">{{ __('Edit Permissions') }}</a>
                            @else
                                <span class="text-xs font-medium text-ink-muted">{{ __('Protected') }}</span>
                            @endif
                        @endcan
                    </div>
                </div>
            @empty
                <div class="card-institutional p-8 text-center text-sm text-ink-muted sm:col-span-2 xl:col-span-3">{{ __('No roles found.') }}</div>
            @endforelse
        </div>
    </div>
@endsection
