@extends('layouts.app')

@section('title', __('Edit Dataset'))

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Edit Dataset') }}</h1>
            <p class="text-gray-600 mt-1">{{ __('Update dataset configuration') }}</p>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <form method="POST" action="{{ route('datasets.update', $dataset) }}" class="p-6 space-y-6">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Name') }}</label>
                        <input type="text" name="name" id="name" value="{{ $dataset->name }}" required readonly
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm bg-gray-100 cursor-not-allowed">
                        <p class="mt-1 text-xs text-gray-500">{{ __('Dataset name cannot be changed') }}</p>
                    </div>

                    <div>
                        <label for="display_name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Display Name') }}</label>
                        <input type="text" name="display_name" id="display_name" value="{{ $dataset->display_name }}" required
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        @error('display_name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Description') }}</label>
                    <textarea name="description" id="description" rows="3"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">{{ $dataset->description }}</textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="dataset_type" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Dataset Type') }}</label>
                        <select name="dataset_type" id="dataset_type" required
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="official_layer" {{ $dataset->dataset_type === 'official_layer' ? 'selected' : '' }}>Official Layer</option>
                            <option value="additional_table" {{ $dataset->dataset_type === 'additional_table' ? 'selected' : '' }}>Additional Table</option>
                        </select>
                        @error('dataset_type')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="source_name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Source Name') }}</label>
                        <input type="text" name="source_name" id="source_name" value="{{ $dataset->source_name }}"
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        @error('source_name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="source_format" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Source Format') }}</label>
                        <input type="text" name="source_format" id="source_format" value="{{ $dataset->source_format }}"
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        @error('source_format')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Is Active') }}</label>
                        <div class="mt-1 flex items-center">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" id="is_active" value="1" {{ $dataset->is_active ? 'checked' : '' }}
                                class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <label for="is_active" class="ml-2 text-sm text-gray-700">{{ __('Active') }}</label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Is Spatial') }}</label>
                        <div class="mt-1 flex items-center">
                            <input type="hidden" name="is_spatial" value="0">
                            <input type="checkbox" name="is_spatial" id="is_spatial" value="1" {{ $dataset->is_spatial ? 'checked' : '' }}
                                class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500" onchange="toggleSpatialFields(this)">
                            <label for="is_spatial" class="ml-2 text-sm text-gray-700">{{ __('Spatial (Geometry)') }}</label>
                        </div>
                    </div>
                </div>

                <div id="spatial-fields" class="{{ $dataset->is_spatial ? '' : 'hidden' }} grid grid-cols-1 md:grid-cols-2 gap-6" style="display: {{ $dataset->is_spatial ? 'grid' : 'none' }};">
                    <div>
                        <label for="geometry_type" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Geometry Type') }}</label>
                        <select name="geometry_type" id="geometry_type" required
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="">{{ __('Select Geometry Type') }}</option>
                            <option value="Point" {{ $dataset->geometry_type === 'Point' ? 'selected' : '' }}>Point</option>
                            <option value="MultiPoint" {{ $dataset->geometry_type === 'MultiPoint' ? 'selected' : '' }}>MultiPoint</option>
                            <option value="LineString" {{ $dataset->geometry_type === 'LineString' ? 'selected' : '' }}>LineString</option>
                            <option value="MultiLineString" {{ $dataset->geometry_type === 'MultiLineString' ? 'selected' : '' }}>MultiLineString</option>
                            <option value="Polygon" {{ $dataset->geometry_type === 'Polygon' ? 'selected' : '' }}>Polygon</option>
                            <option value="MultiPolygon" {{ $dataset->geometry_type === 'MultiPolygon' ? 'selected' : '' }}>MultiPolygon</option>
                        </select>
                        @error('geometry_type')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-gray-500">{{ __('Required for spatial datasets') }}</p>
                    </div>

                    <div>
                        <label for="srid" class="block text-sm font-medium text-gray-700 mb-1">{{ __('SRID') }}</label>
                        <input type="number" name="srid" id="srid" value="{{ $dataset->srid }}" min="1" max="999999"
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            placeholder="{{ __('e.g. 4326') }}">
                        @error('srid')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-gray-500">{{ __('Spatial Reference System Identifier (e.g. 4326 for WGS84)') }}</p>
                    </div>
                </div>

                <div class="mt-6 pt-6 border-t border-gray-200">
                    <div class="flex justify-end space-x-3">
                        <a href="{{ route('datasets.index') }}" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            {{ __('Cancel') }}
                        </a>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            {{ __('Update Dataset') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        function toggleSpatialFields(checkbox) {
            const spatialFields = document.getElementById('spatial-fields');
            const geometryType = document.getElementById('geometry_type');
            const srid = document.getElementById('srid');

            if (checkbox.checked) {
                spatialFields.classList.remove('hidden');
                spatialFields.style.display = 'grid';
                geometryType.required = true;
                srid.required = true;
            } else {
                spatialFields.classList.add('hidden');
                spatialFields.style.display = 'none';
                geometryType.required = false;
                srid.required = false;
                // Clear values when hidden
                document.getElementById('geometry_type').value = '';
                document.getElementById('srid').value = '';
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            const isSpatial = document.getElementById('is_spatial');
            if (isSpatial) {
                toggleSpatialFields(isSpatial);
            }
        });
    </script>
@endsection
