@extends('layouts.app')

@section('title', __('Dashboard'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700 p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Total Datasets') }}</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($totalDatasets) }}</p>
                </div>
                <div class="h-12 w-12 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V21a2 2 0 01-2 2h-6a2 2 0 01-2-2v-4.5" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700 p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Total Records') }}</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($totalRecords) }}</p>
                </div>
                <div class="h-12 w-12 bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700 p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Spatial Datasets') }}</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($spatialDatasets) }}</p>
                </div>
                <div class="h-12 w-12 bg-purple-100 dark:bg-purple-900/30 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700 p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('GIS Features') }}</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($gisFeatures) }}</p>
                </div>
                <div class="h-12 w-12 bg-orange-100 dark:bg-orange-900/30 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Datasets & Quick Actions -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Recent Datasets -->
        <div class="bg-white dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700 shadow-sm">
            <div class="p-4 border-b border-gray-200 dark:border-slate-700 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Recent Datasets') }}</h2>
                <a href="{{ route('datasets.index') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300">{{ __('View All') }}</a>
            </div>
            <div class="divide-y divide-gray-200 dark:divide-slate-700">
                @if($recentDatasets->count() > 0)
                    @foreach($recentDatasets as $dataset)
                    <div class="p-4 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3 min-w-0">
                                <div class="h-10 w-10 rounded-lg flex items-center justify-center
                                    @if($dataset->is_spatial)
                                        bg-blue-100 dark:bg-blue-900/30
                                    @else
                                        bg-gray-100 dark:bg-slate-700
                                    @endif
                                ">
                                    @if($dataset->is_spatial)
                                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z" />
                                        </svg>
                                    @else
                                        <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V21a2 2 0 01-2 2h-6a2 2 0 01-2-2v-4.5" />
                                        </svg>
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $dataset->display_name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $dataset->name }}</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2 text-sm text-gray-500 dark:text-gray-400">
                                <span>{{ number_format($dataset->records_count ?? 0) }} {{ __('records') }}</span>
                                @if($dataset->features_count > 0)
                                    <span class="text-blue-600 dark:text-blue-400">· {{ number_format($dataset->features_count) }} {{ __('features') }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="mt-2 flex items-center space-x-2">
                            <a href="{{ route('datasets.show', $dataset) }}" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300">{{ __('View') }}</a>
                            <a href="{{ route('datasets.records.index', $dataset) }}" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">{{ __('Records') }}</a>
                            @if($dataset->is_spatial && $dataset->is_active)
                                <a href="{{ route('map.index') }}?dataset={{ $dataset->id }}" class="text-sm text-purple-600 dark:text-purple-400 hover:text-purple-700 dark:hover:text-purple-300">{{ __('Map') }}</a>
                            @endif
                        </div>
                    </div>
                    @endforeach
                @else
                    <div class="p-8 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V21a2 2 0 01-2 2h-6a2 2 0 01-2-2v-4.5" />
                        </svg>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ __('No datasets yet') }}</p>
                        @can('datasets.create')
                            <a href="{{ route('datasets.create') }}" class="mt-4 inline-block px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">{{ __('Create Dataset') }}</a>
                        @endcan
                    </div>
                @endif
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700 shadow-sm">
            <div class="p-4 border-b border-gray-200 dark:border-slate-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Quick Actions') }}</h2>
            </div>
            <div class="p-4 space-y-3">
                @can('datasets.create')
                <a href="{{ route('datasets.create') }}" class="flex items-center space-x-3 p-3 rounded-lg border border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
                    <div class="h-10 w-10 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Create New Dataset') }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Add a new dataset to the system') }}</p>
                    </div>
                </a>
                @endcan

                @if($spatialDatasets > 0)
                <a href="{{ route('map.index') }}" class="flex items-center space-x-3 p-3 rounded-lg border border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
                    <div class="h-10 w-10 bg-purple-100 dark:bg-purple-900/30 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Open GIS Map') }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Visualize spatial data on the map') }}</p>
                    </div>
                </a>
                @endif

                <a href="{{ route('datasets.index') }}" class="flex items-center space-x-3 p-3 rounded-lg border border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
                    <div class="h-10 w-10 bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V21a2 2 0 01-2 2h-6a2 2 0 01-2-2v-4.5" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Browse All Datasets') }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('View and manage all datasets') }}</p>
                    </div>
                </a>

                @can('users.view')
                <a href="{{ route('users.index') }}" class="flex items-center space-x-3 p-3 rounded-lg border border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
                    <div class="h-10 w-10 bg-orange-100 dark:bg-orange-900/30 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Manage Users') }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('View and manage system users') }}</p>
                    </div>
                </a>
                @endcan
            </div>
        </div>
    </div>

    <!-- System Status -->
    <div class="bg-white dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700 shadow-sm">
        <div class="p-4 border-b border-gray-200 dark:border-slate-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('System Status') }}</h2>
        </div>
        <div class="p-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="flex items-center space-x-3 p-3 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                    <div class="h-2 w-2 rounded-full bg-green-500"></div>
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Database') }}</p>
                        <p class="text-xs text-green-700 dark:text-green-400">{{ $systemStatus['database'] }}</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3 p-3 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                    <div class="h-2 w-2 rounded-full bg-green-500"></div>
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ __('API') }}</p>
                        <p class="text-xs text-green-700 dark:text-green-400">{{ $systemStatus['api'] }}</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3 p-3 rounded-lg
                    @if($systemStatus['gis'] === 'available')
                        bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800
                    @else
                        bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800
                    @endif
                ">
                    <div class="h-2 w-2 rounded-full
                        @if($systemStatus['gis'] === 'available')
                            bg-blue-500
                        @else
                            bg-yellow-500
                        @endif
                    "></div>
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ __('GIS') }}</p>
                        <p class="text-xs
                            @if($systemStatus['gis'] === 'available')
                                text-blue-700 dark:text-blue-400
                            @else
                                text-yellow-700 dark:text-yellow-400
                            @endif
                        ">
                            @if($systemStatus['gis'] === 'available')
                                {{ $spatialDatasets }} {{ __('spatial datasets available') }}
                            @else
                                {{ __('No spatial data configured') }}
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection