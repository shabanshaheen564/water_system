@extends('layouts.app')

@section('title', __('Permissions'))

@section('content')
    <div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">

        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold leading-[1.5] text-ink">
                    {{ __('Permissions') }}
                </h2>

                <p class="mt-1 text-sm text-ink-secondary">
                    {{ __('View available system permissions and their role assignments') }}
                </p>
            </div>

            @can('roles.view')
                <a
                    href="{{ route('roles.index') }}"
                    class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm font-medium text-ink hover:bg-surface-1"
                >
                    {{ __('Edit Permissions') }}
                </a>
            @endcan
        </div>

        <div data-enter class="card-institutional overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table-institutional">
                    <thead>
                        <tr>
                            <th>{{ __('Permission') }}</th>
                            <th>{{ __('Guard') }}</th>
                            <th>{{ __('Assigned Roles') }}</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($permissions as $permission)
                            <tr class="hover:bg-surface-1">
                                <td>
                                    <div class="text-sm font-medium text-ink">
                                        {{ __('messages.permissions.' . $permission->name) }}
                                    </div>

                                    <code class="ltr-value text-xs text-ink-muted">
                                        {{ $permission->name }}
                                    </code>
                                </td>

                                <td class="whitespace-nowrap text-sm text-ink-muted ltr-value">
                                    {{ $permission->guard_name }}
                                </td>

                                <td>
                                    <div class="flex flex-wrap gap-1.5">
                                        @forelse($permission->roles as $role)
                                            <span class="inline-flex rounded-md border border-border bg-surface-1 px-2 py-1 text-xs font-medium text-ink-secondary">
                                                {{ __('messages.roles.' . $role->name) }}
                                            </span>
                                        @empty
                                            <span class="text-sm text-ink-muted">—</span>
                                        @endforelse
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-12 text-center text-sm text-ink-muted">
                                    {{ __('No permissions found.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
@endsection