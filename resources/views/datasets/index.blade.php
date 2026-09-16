@extends('layouts.app')

@section('title', __('Datasets'))

@section('content')
    <div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
        <div class="mb-6 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold leading-[1.5] text-ink">{{ __('Datasets') }}</h2>
                <p class="mt-1 text-sm text-ink-secondary">{{ __('Browse and manage datasets') }}</p>
            </div>
            @can('datasets.create')
                <a href="{{ route('datasets.create') }}" class="shrink-0 rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                    {{ __('Create Dataset') }}
                </a>
            @endcan
        </div>

        <div class="card-institutional overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table-institutional">
                    <thead>
                        <tr>
                            <th>{{ __('Display Name') }}</th>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Spatial') }}</th>
                            <th>{{ __('Geometry Type') }}</th>
                            <th>{{ __('SRID') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Records') }}</th>
                            <th>{{ __('Features') }}</th>
                            <th>{{ __('Fields') }}</th>
                            <th>{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($datasets as $dataset)
                            <tr class="hover:bg-surface-1">
                                <td>
                                    <div class="text-sm font-medium text-ink">{{ $dataset->display_name }}</div>
                                    @if($dataset->description)
                                        <div class="max-w-xs truncate text-sm text-ink-muted">{{ $dataset->description }}</div>
                                    @endif
                                </td>
                                <td><code class="ltr-value text-sm text-ink-secondary">{{ $dataset->name }}</code></td>
                                <td>
                                    <span class="inline-flex rounded-md border border-border bg-surface-1 px-2 py-1 text-xs font-medium text-ink-secondary">
                                        {{ $dataset->dataset_type === 'official_layer' ? __('Official Layer') : __('Additional Table') }}
                                    </span>
                                </td>
                                <td>
                                    <span class="inline-flex rounded-md border px-2 py-1 text-xs font-medium {{ $dataset->is_spatial ? 'border-info bg-info-surface text-info' : 'border-border bg-surface-1 text-ink-secondary' }}">
                                        {{ $dataset->is_spatial ? __('Yes') : __('No') }}
                                    </span>
                                </td>
                                <td>
                                    @if($dataset->is_spatial)
                                        <code class="ltr-value text-sm text-ink-secondary">{{ $dataset->geometry_type }}</code>
                                    @else
                                        <span class="text-ink-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($dataset->srid)
                                        <code class="ltr-value text-sm text-ink-secondary">{{ $dataset->srid }}</code>
                                    @else
                                        <span class="text-ink-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="inline-flex rounded-md border px-2 py-1 text-xs font-medium {{ $dataset->is_active ? 'border-success bg-success-surface text-success' : 'border-border bg-surface-1 text-ink-secondary' }}">
                                        {{ $dataset->is_active ? __('Active') : __('Inactive') }}
                                    </span>
                                </td>
                                <td class="text-sm text-ink">{{ number_format($dataset->records_count ?? 0) }}</td>
                                <td class="text-sm text-ink">{{ number_format($dataset->features_count ?? 0) }}</td>
                                <td class="text-sm text-ink">{{ $dataset->fields_count ?? 0 }}</td>
                                <td>
                                    <div class="flex flex-wrap items-center gap-3 text-sm font-medium">
                                        @if($dataset->is_spatial && $dataset->is_active)
                                            <a href="{{ route('map.index') }}?dataset={{ $dataset->id }}" class="text-brand-600 hover:text-brand-700">{{ __('View on Map') }}</a>
                                        @else
                                            <span class="text-ink-muted">{{ __('View on Map') }}</span>
                                        @endif
                                        <a href="{{ route('datasets.show', $dataset) }}" class="text-ink-secondary hover:text-ink">{{ __('View') }}</a>
                                        @can('datasets.update')
                                            <a href="{{ route('datasets.edit', $dataset) }}" class="text-ink-secondary hover:text-ink">{{ __('Edit') }}</a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="py-12 text-center text-sm text-ink-muted">{{ __('No datasets found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
