@extends('layouts.app')

@section('title', __('Edit Role Permissions'))

@section('content')
<div class="mx-auto max-w-5xl p-4 sm:p-6 lg:p-8">
    <div class="mb-6">
        <h2 class="text-xl font-semibold text-ink">{{ __('Edit Role Permissions') }}</h2>
        <p class="mt-1 text-sm text-ink-secondary">{{ __('Manage the permissions inherited by this role') }}: <strong>{{ __('messages.roles.' . $role->name) }}</strong></p>
    </div>

    <form method="POST" action="{{ route('roles.update', $role) }}" class="card-institutional p-6">
        @csrf
        @method('PUT')

        @php($selected = old('permissions', $role->permissions->pluck('id')->all()))
        @php($groups = $permissions->groupBy(fn ($permission) => str_contains($permission->name, '.') ? str($permission->name)->before('.')->toString() : 'general'))

        <div class="grid gap-6 md:grid-cols-2">
            @foreach($groups as $group => $groupPermissions)
                <section class="rounded-md border border-border bg-surface-1 p-4">
                    <h3 class="mb-3 text-sm font-semibold text-ink">{{ $group === 'general' ? __('General') : __('messages.permission_groups.' . $group) }}</h3>
                    <div class="space-y-2">
                        @foreach($groupPermissions as $permission)
                            <label class="flex items-start gap-3 rounded-md border border-border bg-white p-3">
                                <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked(in_array($permission->id, $selected)) class="mt-0.5 h-4 w-4 rounded border-border-strong text-brand-600">
                                <span>
                                    <span class="block text-sm font-medium text-ink">{{ __('messages.permissions.' . $permission->name) }}</span>
                                    <code class="ltr-value text-xs text-ink-muted">{{ $permission->name }}</code>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        @error('permissions')<p class="mt-4 text-sm text-danger">{{ $message }}</p>@enderror
        <div class="mt-6 flex justify-end gap-3 border-t border-border pt-5">
            <a href="{{ route('roles.index') }}" class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm font-medium text-ink hover:bg-surface-1">{{ __('Cancel') }}</a>
            <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Save Changes') }}</button>
        </div>
    </form>
</div>
@endsection
