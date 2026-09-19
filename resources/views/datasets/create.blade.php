@extends('layouts.app')

@section('title', __('Create Dataset'))

@section('content')
    <div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
        <div class="mb-6">
            <h2 class="text-xl font-semibold leading-[1.5] text-ink">{{ __('Create Dataset') }}</h2>
            <p class="mt-1 text-sm text-ink-secondary">{{ __('Create a new dataset for managing spatial or tabular data') }}</p>
        </div>
        <div data-enter class="card-institutional overflow-hidden">
            <form method="POST" action="{{ route('datasets.store') }}" class="space-y-6 p-6">
                @csrf
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <label for="name" class="mb-1 block text-sm font-medium text-ink">{{ __('Name') }}</label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" required class="input-institutional mt-1 block w-full px-3 py-2 text-sm outline-none focus:border-brand-600" placeholder="wells, parcels, municipalities">
                        @error('name')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                        <p class="mt-1 text-xs text-ink-muted">{{ __('Alphanumeric and underscores only') }}</p>
                    </div>
                    <div>
                        <label for="display_name" class="mb-1 block text-sm font-medium text-ink">{{ __('Display Name') }}</label>
                        <input type="text" name="display_name" id="display_name" value="{{ old('display_name') }}" required class="input-institutional mt-1 block w-full px-3 py-2 text-sm outline-none focus:border-brand-600" placeholder="{{ __('e.g. Water Wells') }}">
                        @error('display_name')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label for="description" class="mb-1 block text-sm font-medium text-ink">{{ __('Description') }}</label>
                    <textarea name="description" id="description" rows="3" class="input-institutional mt-1 block w-full px-3 py-2 text-sm outline-none focus:border-brand-600" placeholder="{{ __('Optional description of the dataset') }}">{{ old('description') }}</textarea>
                    @error('description')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <label for="dataset_type" class="mb-1 block text-sm font-medium text-ink">{{ __('Dataset Type') }}</label>
                        <select name="dataset_type" id="dataset_type" required class="input-institutional mt-1 block w-full px-3 py-2 text-sm outline-none focus:border-brand-600">
                            <option value="official_layer" {{ old('dataset_type') === 'official_layer' ? 'selected' : '' }}>{{ __('Official Layer') }}</option>
                            <option value="additional_table" {{ old('dataset_type') === 'additional_table' ? 'selected' : '' }}>{{ __('Additional Table') }}</option>
                        </select>
                        @error('dataset_type')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="source_name" class="mb-1 block text-sm font-medium text-ink">{{ __('Source Name') }}</label>
                        <input type="text" name="source_name" id="source_name" value="{{ old('source_name') }}" class="input-institutional mt-1 block w-full px-3 py-2 text-sm outline-none focus:border-brand-600" placeholder="{{ __('e.g. Municipality GIS, Survey Dept') }}">
                        @error('source_name')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div>
                        <label for="source_format" class="mb-1 block text-sm font-medium text-ink">{{ __('Source Format') }}</label>
                        <input type="text" name="source_format" id="source_format" value="{{ old('source_format') }}" class="input-institutional mt-1 block w-full px-3 py-2 text-sm outline-none focus:border-brand-600" placeholder="{{ __('e.g. Shapefile, GeoJSON, CSV') }}">
                        @error('source_format')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">{{ __('Is Active') }}</label>
                        <div class="mt-2 flex items-center gap-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="h-4 w-4 rounded border-border-strong text-brand-600 focus:ring-brand-600"><label for="is_active" class="text-sm text-ink-secondary">{{ __('Active') }}</label></div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">{{ __('Is Spatial') }}</label>
                        <div class="mt-2 flex items-center gap-2"><input type="hidden" name="is_spatial" value="0"><input type="checkbox" name="is_spatial" id="is_spatial" value="1" {{ old('is_spatial') ? 'checked' : '' }} data-spatial-toggle class="h-4 w-4 rounded border-border-strong text-brand-600 focus:ring-brand-600"><label for="is_spatial" class="text-sm text-ink-secondary">{{ __('Spatial (Geometry)') }}</label></div>
                    </div>
                </div>
                <div id="spatial-fields" class="grid grid-cols-1 gap-6 md:grid-cols-2 {{ old('is_spatial') ? '' : 'hidden' }}">
                    <div>
                        <label for="geometry_type" class="mb-1 block text-sm font-medium text-ink">{{ __('Geometry Type') }}</label>
                        <select name="geometry_type" id="geometry_type" class="input-institutional mt-1 block w-full px-3 py-2 text-sm outline-none focus:border-brand-600">
                            <option value="">{{ __('Select Geometry Type') }}</option>
                            @foreach(['Point','MultiPoint','LineString','MultiLineString','Polygon','MultiPolygon'] as $geometryType)
                                <option value="{{ $geometryType }}" {{ old('geometry_type') === $geometryType ? 'selected' : '' }}>{{ $geometryType }}</option>
                            @endforeach
                        </select>
                        @error('geometry_type')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                        <p class="mt-1 text-xs text-ink-muted">{{ __('Required for spatial datasets') }}</p>
                    </div>
                    <div>
                        <label for="srid" class="mb-1 block text-sm font-medium text-ink">{{ __('SRID') }}</label>
                        <input type="number" name="srid" id="srid" value="{{ old('srid') }}" min="1" max="999999" class="input-institutional mt-1 block w-full px-3 py-2 text-sm outline-none focus:border-brand-600" placeholder="4326">
                        @error('srid')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                        <p class="mt-1 text-xs text-ink-muted">{{ __('Spatial Reference System Identifier (e.g. 4326 for WGS84)') }}</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-6 md:grid-cols-4">
                    <div>
                        <label for="map_order" class="mb-1 block text-sm font-medium text-ink">ترتيب الطبقة</label>
                        <input type="number" name="map_order" id="map_order" value="{{ old('map_order', 0) }}" min="0" max="999999" class="input-institutional mt-1 block w-full px-3 py-2 text-sm" dir="ltr">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">الظهور الافتراضي</label>
                        <div class="mt-2 flex items-center gap-2">
                            <input type="hidden" name="default_visible" value="0">
                            <input type="checkbox" name="default_visible" id="default_visible" value="1" {{ old('default_visible', true) ? 'checked' : '' }} class="h-4 w-4 rounded border-border-strong text-brand-600">
                            <label for="default_visible" class="text-sm text-ink-secondary">إظهار على الخريطة</label>
                        </div>
                    </div>
                    <div>
                        <label for="map_opacity" class="mb-1 block text-sm font-medium text-ink">شفافية الطبقة</label>
                        <input type="number" name="map_opacity" id="map_opacity" value="{{ old('map_opacity', 1) }}" min="0" max="1" step="0.05" class="input-institutional mt-1 block w-full px-3 py-2 text-sm" dir="ltr">
                    </div>
                    <div>
                        <label for="display_color" class="mb-1 block text-sm font-medium text-ink">لون الطبقة</label>
                        <input type="color" name="display_color" id="display_color" value="{{ old('display_color', '#475467') }}" class="mt-1 h-10 w-full cursor-pointer rounded-md border border-border-strong bg-white p-1">
                    </div>
                </div>
                <div class="flex justify-end gap-3 border-t border-border pt-6">
                    <a href="{{ route('datasets.index') }}" class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm font-medium text-ink hover:bg-surface-1">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn-motion rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Create Dataset') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
