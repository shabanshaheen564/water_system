@extends('layouts.app')

@section('title', __('Users'))

@section('content')
    <div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
        <div class="mb-6 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold leading-[1.5] text-ink">{{ __('Users') }}</h2>
                <p class="mt-1 text-sm text-ink-secondary">{{ __('Browse and manage system users') }}</p>
            </div>
            @can('users.create')
                <a href="{{ route('users.create') }}" class="shrink-0 rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Create User') }}</a>
            @endcan
        </div>

        <div class="card-institutional overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table-institutional">
                    <thead>
                        <tr>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Email') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Roles') }}</th>
                            <th>{{ __('Last Login') }}</th>
                            <th>{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                            <tr class="hover:bg-surface-1">
                                <td class="whitespace-nowrap"><div class="text-sm font-medium text-ink">{{ $user->name }}</div></td>
                                <td class="whitespace-nowrap"><div class="ltr-value text-sm text-ink-secondary">{{ $user->email }}</div></td>
                                <td class="whitespace-nowrap">
                                    <span class="inline-flex rounded-md border px-2 py-1 text-xs font-medium {{ $user->is_active ? 'border-success bg-success-surface text-success' : 'border-border bg-surface-1 text-ink-secondary' }}">
                                        {{ $user->is_active ? __('Active') : __('Inactive') }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap">
                                    @if($user->roles->count())
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($user->roles as $role)
                                                <span class="inline-flex rounded-md border border-border bg-surface-1 px-2 py-1 text-xs font-medium text-ink-secondary">
                                                    {{ __('messages.roles.' . $role->name) }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-ink-muted">—</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-sm text-ink-secondary ltr-value">
                                    {{ $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i') : '—' }}
                                </td>
                                <td class="whitespace-nowrap">
                                    <div class="flex items-center gap-3 text-sm font-medium">
                                        <a href="{{ route('users.show', $user) }}" class="text-brand-600 hover:text-brand-700">{{ __('View') }}</a>
                                        @can('users.update')
                                            <a href="{{ route('users.edit', $user) }}" class="text-ink-secondary hover:text-ink">{{ __('Edit') }}</a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-12 text-center text-sm text-ink-muted">{{ __('No users found.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $users->links() }}
        </div>
    </div>
@endsection
