@extends('layouts.app')

@section('content')
    <div class="p-4 sm:p-6 lg:p-8 max-w-3xl mx-auto">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Edit User') }}</h2>
            <p class="mt-1 text-gray-600 dark:text-gray-400">{{ __('Update account information, status, and roles') }}</p>
        </div>

        <form method="POST" action="{{ route('users.update', $user) }}" class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 p-6 space-y-6">
            @csrf
            @method('PUT')

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Name') }}</label>
                <input id="name" name="name" value="{{ old('name', $user->name) }}" required class="mt-1 block w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white focus:border-blue-500 focus:ring-blue-500">
                @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Email') }}</label>
                <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required class="mt-1 block w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white focus:border-blue-500 focus:ring-blue-500">
                @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Roles') }}</label>
                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    @php($selectedRoles = old('roles', $user->roles->pluck('name')->all()))
                    @foreach($roles as $role)
                        <label class="flex items-center gap-2 rounded-lg border border-gray-200 dark:border-slate-700 p-3">
                            <input type="checkbox" name="roles[]" value="{{ $role->name }}" @checked(in_array($role->name, $selectedRoles, true)) class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $role->name }}</span>
                        </label>
                    @endforeach
                </div>
                @error('roles')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <label class="flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active)) class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Active') }}</span>
            </label>
            @error('is_active')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

            <div class="flex items-center justify-end gap-3 pt-2">
                <a href="{{ route('users.show', $user) }}" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700">{{ __('Cancel') }}</a>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">{{ __('Save Changes') }}</button>
            </div>
        </form>
    </div>
@endsection
