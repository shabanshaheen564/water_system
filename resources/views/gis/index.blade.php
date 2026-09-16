@extends('layouts.app')

@section('title', __('GIS Map'))

@section('content')
<div class="mx-auto max-w-[1600px] p-4 sm:p-6 lg:p-8">
    <div class="mb-6"><h2 class="text-xl font-semibold leading-[1.5] text-ink">{{ __('GIS Map') }}</h2><p class="mt-1 text-sm text-ink-secondary">{{ __('Interactive map for spatial data visualization') }}</p></div>
    <div class="grid gap-4 xl:grid-cols-[280px_minmax(0,1fr)]">
        <aside data-enter class="card-institutional p-4">
            <h3 class="mb-3 text-sm font-semibold text-ink">{{ __('Layers') }}</h3>
            <div id="layer-list" class="space-y-2">
                @forelse($spatialDatasets as $dataset)
                    <div class="layer-item border border-border p-3" data-dataset-id="{{ $dataset->id }}">
                        <div class="flex items-start justify-between gap-3">
                            <label for="layer-toggle-{{ $dataset->id }}" class="flex min-w-0 items-center gap-2">
                                <input type="checkbox" class="layer-toggle h-4 w-4 rounded border-border-strong text-brand-600 focus:ring-brand-600" data-dataset-id="{{ $dataset->id }}" id="layer-toggle-{{ $dataset->id }}" {{ $loop->first ? 'checked' : '' }}>
                                <span class="truncate text-sm font-medium text-ink">{{ $dataset->display_name }}</span>
                            </label>
                            <span class="shrink-0 text-xs text-ink-muted" dir="ltr">{{ $dataset->geometry_type }}</span>
                        </div>
                        <div class="mt-2 flex items-center justify-between text-xs text-ink-muted"><span>{{ __('Features') }}: {{ $dataset->features_count ?? 0 }}</span><span dir="ltr">SRID: {{ $dataset->srid ?? 4326 }}</span></div>
                    </div>
                @empty
                    <div class="border border-border bg-surface-1 p-5 text-center"><p class="text-sm font-medium text-ink">{{ __('No spatial datasets available') }}</p><p class="mt-1 text-xs text-ink-muted">{{ __('Create a spatial dataset to get started') }}</p></div>
                @endforelse
            </div>
            <div class="mt-4 border-t border-border pt-4"><h3 class="mb-3 text-sm font-semibold text-ink">{{ __('Map Controls') }}</h3><div class="space-y-2"><button id="zoom-to-layers" class="w-full rounded-md border border-border-strong bg-white px-3 py-2 text-sm font-medium text-ink hover:bg-surface-1">{{ __('Zoom to Layers') }}</button><button id="reset-view" class="w-full rounded-md border border-border-strong bg-white px-3 py-2 text-sm font-medium text-ink hover:bg-surface-1">{{ __('Reset View') }}</button></div></div>
        </aside>
        <section data-enter class="card-institutional overflow-hidden"><div id="map" data-msg-load-failed="{{ __('messages.map.layer_load_failed') }}" data-msg-feature-details="{{ __('messages.map.feature_details') }}"></div></section>
    </div>
</div>
@endsection