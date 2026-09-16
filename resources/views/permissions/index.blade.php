@extends('layouts.app')

@section('title', __('Permissions'))

@section('content')
    <div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
        <div class="mb-6">
            <h2 class="text-xl font-semibold leading-[1.5] text-ink">{{ __('Permissions') }}</h2>
            <p class="mt-1 text-sm text-ink-secondary">{{ __('View available system permissions and their role assignments') }}</p>
        </div>

        <div class="card-institutional overflow-hidden">
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
                                <td class="whitespace-nowrap text-sm font-medium"><code class="ltr-value text-ink">{{ $permission->name }}</code></td>
                                <td class="whitespace-nowrap text-sm text-ink-muted ltr-value">{{ $permission->guard_name }}</td>
                                <td class="whitespace-nowrap text-sm text-ink-secondary">{{ $permission->roles_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-12 text-center text-sm text-ink-muted">{{ __('No permissions found.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
