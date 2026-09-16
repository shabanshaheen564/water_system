@extends('layouts.app')

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="mb-6 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('User Details') }}</h2>
                <p class="mt-1 text-gray-600 dark:text-gray-400">{{ __('Account information and assigned roles') }}</p>
            </div>
            <a href="{{ route('users.index') }}" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700">
                {{ __('Back to Users') }}
            </a>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-5">{{ __('Account') }}</h3>
                <dl class="space-y-4">
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">{{ __('Name') }}</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $user->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">{{ __('Email') }}</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $user->email }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">{{ __('Status') }}</dt>
                        <dd class="mt-1">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $user->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-800 dark:bg-slate-700 dark:text-gray-400' }}">
                                {{ $user->is_active ? __('Active') : __('Inactive') }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">{{ __('Last Login') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $user->last_login_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">{{ __('Created At') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $user->created_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-5">{{ __('Assigned Roles') }}</h3>
                @if($user->roles->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No roles assigned.') }}</p>
                @else
                    <div class="space-y-4">
                        @foreach($user->roles as $role)
                            <div class="rounded-lg border border-gray-200 dark:border-slate-700 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $role->name }}</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $role->permissions->count() }} {{ __('permissions') }}</span>
                                </div>
                                @if($role->permissions->isNotEmpty())
                                    <div class="mt-3 flex flex-wrap gap-1.5">
                                        @foreach($role->permissions as $permission)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400 text-xs">
                                                {{ $permission->name }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
