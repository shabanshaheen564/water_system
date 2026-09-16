@extends('layouts.app')

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Roles') }}</h2>
            <p class="mt-1 text-gray-600 dark:text-gray-400">{{ __('View system roles and their current usage') }}</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @forelse($roles as $role)
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">{{ $role->name }}</h3>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $role->guard_name }}</p>
                        </div>
                        <span class="inline-flex items-center px-2 py-1 rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400 text-xs font-medium">
                            {{ $role->permissions_count }} {{ __('permissions') }}
                        </span>
                    </div>
                    <div class="mt-5 pt-4 border-t border-gray-200 dark:border-slate-700">
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $role->users_count }} {{ __('assigned users') }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="sm:col-span-2 xl:col-span-3 bg-white dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700 p-8 text-center text-gray-500 dark:text-gray-400">
                    {{ __('No roles found.') }}
                </div>
            @endforelse
        </div>
    </div>
@endsection
