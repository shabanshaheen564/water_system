@extends('layouts.app')
@section('title', __('Edit User'))
@section('content')
<div class="mx-auto max-w-5xl p-4 sm:p-6 lg:p-8">
    <div class="mb-6">
        <h2 class="text-xl font-semibold text-ink">{{ __('Edit User') }}</h2>
        <p class="mt-1 text-sm text-ink-secondary">{{ __('Update account information, status, roles, and custom permissions') }}</p>
    </div>

    @if($errors->has('delete'))
        <div class="mb-5 rounded-md border border-danger bg-danger-surface p-4 text-sm text-danger">{{ $errors->first('delete') }}</div>
    @endif

    <form method="POST" action="{{ route('users.update', $user) }}" class="card-institutional space-y-6 p-6">
        @csrf @method('PUT')
        <div class="grid gap-6 sm:grid-cols-2">
            <div><label for="name" class="mb-1 block text-sm font-medium text-ink">{{ __('Name') }}</label><input id="name" name="name" value="{{ old('name', $user->name) }}" required class="input-institutional w-full text-sm">@error('name')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror</div>
            <div><label for="email" class="mb-1 block text-sm font-medium text-ink">{{ __('Email') }}</label><input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required class="input-institutional w-full text-sm" dir="ltr">@error('email')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror</div>
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-ink">{{ __('Roles') }}</label>
            <div class="grid gap-2 sm:grid-cols-2">
                @php($selectedRoles = old('roles', $user->roles->pluck('name')->all()))
                @foreach($roles as $role)
                    <label class="flex items-center gap-2 border border-border p-3"><input type="checkbox" name="roles[]" value="{{ $role->name }}" @checked(in_array($role->name, $selectedRoles, true)) class="h-4 w-4 rounded border-border-strong text-brand-600"><span class="text-sm text-ink">{{ __('messages.roles.'.$role->name) }}</span></label>
                @endforeach
            </div>
            @error('roles')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
        </div>

        @if(!$user->hasRole('System Owner'))
            @php($selectedPermissions = old('permissions', $user->permissions->pluck('id')->all()))
            @php($permissionGroups = $permissions->groupBy(fn ($permission) => str_contains($permission->name, '.') ? str($permission->name)->before('.')->toString() : 'general'))
            <div>
                <div class="mb-2">
                    <h3 class="text-sm font-semibold text-ink">{{ __('Custom Account Permissions') }}</h3>
                    <p class="mt-1 text-xs text-ink-muted">{{ __('These permissions apply directly to this account in addition to its role permissions.') }}</p>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach($permissionGroups as $group => $groupPermissions)
                        <section class="rounded-md border border-border bg-surface-1 p-4">
                            <h4 class="mb-3 text-sm font-semibold text-ink">{{ $group === 'general' ? __('General') : __('messages.permission_groups.' . $group) }}</h4>
                            <div class="space-y-2">
                                @foreach($groupPermissions as $permission)
                                    <label class="flex items-start gap-3 rounded-md border border-border bg-white p-3">
                                        <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked(in_array($permission->id, $selectedPermissions)) class="mt-0.5 h-4 w-4 rounded border-border-strong text-brand-600">
                                        <span><span class="block text-sm font-medium text-ink">{{ __('messages.permissions.' . $permission->name) }}</span><code class="ltr-value text-xs text-ink-muted">{{ $permission->name }}</code></span>
                                    </label>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>
                @error('permissions')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
            </div>
        @else
            <div class="rounded-md border border-border bg-surface-1 p-4 text-sm text-ink-secondary">
                {{ __('System Owner has protected full system permissions. Custom permissions are managed through the protected System Owner role.') }}
            </div>
        @endif

        <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active)) class="h-4 w-4 rounded border-border-strong text-brand-600"><span class="text-sm text-ink-secondary">{{ __('Active') }}</span></label>
        @error('is_active')<p class="text-sm text-danger">{{ $message }}</p>@enderror

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-5">
            <div>
                @can('users.delete')
                    @if(!$user->hasRole('System Owner') && $user->id !== auth()->id())
                        <button type="submit" form="delete-user-form" class="rounded-md border border-danger bg-white px-4 py-2 text-sm font-medium text-danger hover:bg-danger-surface">{{ __('Delete') }}</button>
                    @endif
                @endcan
            </div>
            <div class="flex gap-3">
                <a href="{{ route('users.show', $user) }}" class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm font-medium text-ink hover:bg-surface-1">{{ __('Cancel') }}</a>
                <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Save Changes') }}</button>
            </div>
        </div>
    </form>

    @can('users.delete')
        @if(!$user->hasRole('System Owner') && $user->id !== auth()->id())
            <form id="delete-user-form" method="POST" action="{{ route('users.destroy', $user) }}" class="hidden" onsubmit="return confirm('{{ __('Are you sure?') }}');">@csrf @method('DELETE')</form>
        @endif
    @endcan
</div>
@endsection
