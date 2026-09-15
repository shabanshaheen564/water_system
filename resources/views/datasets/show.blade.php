@extends('layouts.app')

@section('title', __('Dataset Details'))

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $dataset->display_name }}</h1>
                <p class="text-gray-600 mt-1">{{ $dataset->name }}</p>
            </div>
            <div class="flex space-x-3">
                @can('datasets.update')
                    <a href="{{ route('datasets.edit', $dataset) }}" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        {{ __('Edit') }}
                    </a>
                @endcan
                <a href="{{ route('map.index') }}?dataset={{ $dataset->id }}" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    {{ __('View on Map') }}
                </a>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <dl class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Name') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $dataset->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Display Name') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $dataset->display_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Type') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                @if($dataset->dataset_type === 'official_layer')
                                    bg-purple-100 text-purple-800
                                @else
                                    bg-green-100 text-green-800
                                @endif
                            ">
                                {{ $dataset->dataset_type === 'official_layer' ? __('Official Layer') : __('Additional Table') }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Status') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                @if($dataset->is_active)
                                    bg-green-100 text-green-800
                                @else
                                    bg-gray-100 text-gray-800
                                @endif
                            ">
                                {{ $dataset->is_active ? __('Active') : __('Inactive') }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Spatial') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            @if($dataset->is_spatial)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    {{ __('Yes') }} ({{ $dataset->geometry_type }}, SRID: {{ $dataset->srid }})
                                </span>
                            @else
                                <span class="text-gray-400">{{ __('No') }}</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Geometry Type') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $dataset->geometry_type ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('SRID') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $dataset->srid ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Source Name') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $dataset->source_name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Source Format') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $dataset->source_format ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Records') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ number_format($recordsCount ?? 0) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('GIS Features') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ number_format($featuresCount ?? 0) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Fields') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $dataset->fields->count() }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Created By') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $dataset->createdBy->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Created At') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $dataset->created_at->format('Y-m-d H:i') }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        @if($dataset->description)
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ __('Description') }}</h3>
            <p class="text-gray-600 whitespace-pre-wrap">{{ $dataset->description }}</p>
        </div>
        @endif

        @if($dataset->is_spatial && $featuresCount > 0)
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">{{ __('GIS Features') }}</h3>
                <a href="{{ route('map.index') }}?dataset={{ $dataset->id }}" class="px-3 py-1 text-sm text-white bg-blue-600 rounded-md hover:bg-blue-700">
                    {{ __('View on Map') }}
                </a>
            </div>
            <p class="text-sm text-gray-600">{{ $featuresCount }} {{ __('features on map') }}</p>
        </div>
        @endif

        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Fields') }}</h3>
            @if($dataset->fields->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Name') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Display Name') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Type') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Required') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Unique') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Identifier') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Sort') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($dataset->fields->sortBy('sort_order') as $field)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <code class="text-sm text-gray-600 bg-gray-100 px-2 py-1 rounded">{{ $field->name }}</code>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $field->display_name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">
                                            {{ $field->data_type }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($field->is_required)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">{{ __('Yes') }}</span>
                                        @else
                                            <span class="text-gray-400">{{ __('No') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($field->is_unique)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">{{ __('Yes') }}</span>
                                        @else
                                            <span class="text-gray-400">{{ __('No') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($field->is_identifier)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">{{ __('Yes') }}</span>
                                        @else
                                            <span class="text-gray-400">{{ __('No') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $field->sort_order }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-8">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V21a2 2 0 01-2 2h-6a2 2 0 01-2-2v-4.5" />
                    </svg>
                    <p class="mt-2 text-sm text-gray-600">{{ __('No fields defined yet') }}</p>
                    @can('datasets.create')
                        <a href="{{ route('datasets.fields.create', $dataset) }}" class="mt-4 inline-block px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                            {{ __('Add First Field') }}
                        </a>
                    @endcan
                </div>
            @endif
        </div>
    </div>
@endsection
