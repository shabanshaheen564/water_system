@extends('layouts.app')

@section('title', __('GIS Map'))

@section('styles')
    @vite('resources/css/app.css')
    <style>
        #map {
            height: calc(100vh - 200px);
            min-height: 500px;
            width: 100%;
            border-radius: 0.5rem;
        }
        .leaflet-container {
            font-family: inherit;
        }
        .layer-item {
            transition: all 0.2s ease;
        }
        .layer-item:hover {
            background-color: #f3f4f6;
        }
        .layer-item.active {
            background-color: #dbeafe;
            border-left: 3px solid #3b82f6;
        }
        .feature-popup {
            max-width: 300px;
        }
        .feature-popup .property-row {
            display: flex;
            padding: 0.25rem 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .feature-popup .property-key {
            font-weight: 600;
            color: #374151;
            min-width: 120px;
        }
        .feature-popup .property-value {
            color: #6b7280;
            flex: 1;
            word-break: break-word;
        }
        .layer-toggle {
            transition: all 0.2s;
        }
        .layer-toggle:checked {
            background-color: #3b82f6;
            border-color: #3b82f6;
        }
        .layer-loading {
            opacity: 0.6;
            pointer-events: none;
        }
        .layer-loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 16px;
            height: 16px;
            margin: -8px 0 0 -8px;
            border: 2px solid #3b82f6;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .layer-error {
            border-color: #ef4444;
            background-color: #fef2f2;
        }
        .layer-error::after {
            content: attr(data-error);
            display: block;
            margin-top: 0.5rem;
            font-size: 0.75rem;
            color: #dc2626;
        }
        .empty-layers {
            text-align: center;
            padding: 2rem;
            color: #9ca3af;
        }
        .empty-layers svg {
            width: 48px;
            height: 48px;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        .layer-feature-count {
            font-size: 0.75rem;
            color: #6b7280;
            margin-left: 0.5rem;
        }
    </style>
@endsection

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">{{ __('GIS Map') }}</h1>
            <p class="text-gray-600 mt-1">{{ __('Interactive map for spatial data visualization') }}</p>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div id="map"></div>
        </div>
    </div>
@endsection

@section('sidebar')
    <div class="p-4 overflow-y-auto h-full">
        <div class="mb-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-3">{{ __('Layers') }}</h2>
            <div id="layer-list" class="space-y-2">
                @if($spatialDatasets->isEmpty())
                    <div class="empty-layers">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="mx-auto">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 2.25H8.25v11.25H4.5a1.125 1.125 0 00-1.125 1.125v2.25A2.25 2.25 0 004.5 21h15a2.25 2.25 0 002.25-2.25v-2.25a1.125 1.125 0 00-1.125-1.125H6.75v-9A2.25 2.25 0 002.25 3h1.5a1.125 1.125 0 011.125-1.125H12a1.125 1.125 0 011.125 1.125v1.5H21a2.25 2.25 0 012.25 2.25v10.5A2.25 2.25 0 0119.5 22.5H4.5A2.25 2.25 0 012.25 20.25v-4.5c0-.621.504-1.125 1.125-1.125H12a1.125 1.125 0 001.125-1.125V6.75a9.06 9.06 0 00-1.5-.189 10.501 10.501 0 00-8.613 7.5H4.5a1.125 1.125 0 00-1.125 1.125v3.375c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V15a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v2.25c0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75v-1.5a.75.75 0 00-.75-.75H5.25a.75.75 0 01-.75-.75v-1.5a2.25 2.25 0 012.25-2.25h15a2.25 2.25 0 012.25 2.25v2.25c0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75v-1.5a.75.75 0 00-.75-.75H5.25a.75.75 0 01-.75-.75v-1.5a2.25 2.25 0 012.25-2.25h15a2.25 2.25 0 012.25 2.25v2.25c0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75v-1.5a.75.75 0 00-.75-.75H5.25a.75.75 0 01-.75-.75v-1.5a2.25 2.25 0 012.25-2.25h15a2.25 2.25 0 012.25 2.25v2.25c0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75v-1.5a.75.75 0 00-.75-.75H5.25a.75.75 0 01-.75-.75v-1.5a2.25 2.25 0 012.25-2.25h15a2.25 2.25 0 012.25 2.25v2.25c0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75v-1.5a.75.75 0 00-.75-.75H5.25a.75.75 0 01-.75-.75v-1.5a2.25 2.25 0 012.25-2.25h15a2.25 2.25 0 012.25 2.25v2.25c0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75v-1.5a.75.75 0 00-.75-.75H5.25a.75.75 0 01-.75-.75v-1.5a2.25 2.25 0 012.25-2.25h15a2.25 2.25 0 012.25 2.25v2.25c0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75v-1.5a.75.75 0 00-.75-.75H5.25a.75.75 0 01-.75-.75v-1.5a2.25 2.25 0 012.25-2.25h15a2.25 2.25 0 012.25 2.25v2.25c0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75v-1.5a.75.75 0 00-.75-.75H5.25a.75.75 0 01-.75-.75v-1.5" />
                        </svg>
                        <p class="mt-2 text-sm font-medium text-gray-900">{{ __('No spatial datasets available') }}</p>
                        <p class="text-sm text-gray-500 mt-1">{{ __('Create a spatial dataset to get started') }}</p>
                    </div>
                @else
                    <div id="layer-list" class="space-y-2">
                        @foreach($spatialDatasets as $dataset)
                            <div class="layer-item p-3 rounded-lg border border-gray-200" data-dataset-id="{{ $dataset->id }}">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <input type="checkbox" 
                                               class="layer-toggle h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500" 
                                               data-dataset-id="{{ $dataset->id }}"
                                               id="layer-toggle-{{ $dataset->id }}"
                                               {{ $loop->first ? 'checked' : '' }}>
                                        <label for="layer-toggle-{{ $dataset->id }}" class="cursor-pointer flex items-center space-x-2">
                                            <span class="font-medium text-gray-900">{{ $dataset->display_name }}</span>
                                            <span class="ml-2 px-2 py-0.5 text-xs bg-blue-100 text-blue-800 rounded-full">{{ $dataset->geometry_type }}</span>
                                            <span class="layer-feature-count">({{ $dataset->features_count ?? 0 }})</span>
                                        </label>
                                    </div>
                                    <span class="text-xs text-gray-500">SRID: {{ $dataset->srid ?? 4326 }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="border-t border-gray-200 pt-4">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">{{ __('Map Controls') }}</h3>
            <div class="space-y-2">
                <button id="zoom-to-layers" class="w-full px-3 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors flex items-center justify-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" />
                    </svg>
                    <span>{{ __('Zoom to Layers') }}</span>
                </button>
                <button id="reset-view" class="w-full px-3 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors flex items-center justify-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>{{ __('Reset View') }}</span>
                </button>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    @vite('resources/js/app.js')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize map
            const map = L.map('map', {
                center: [31.5, 34.5],
                zoom: 8,
                zoomControl: true,
                attributionControl: true
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            const layers = {};
            const layerToggles = document.querySelectorAll('.layer-toggle');
            const baseUrl = '{{ url("/api") }}';

            // Load initial layer if checked
            layerToggles.forEach(toggle => {
                if (toggle.checked) {
                    loadLayer(toggle.dataset.datasetId, toggle.id);
                }
            });

            // Layer toggle event
            layerToggles.forEach(toggle => {
                toggle.addEventListener('change', function() {
                    const datasetId = this.dataset.datasetId;
                    if (this.checked) {
                        loadLayer(datasetId, this.id);
                    } else {
                        removeLayer(datasetId);
                    }
                });
            });

            function loadLayer(datasetId, toggleId) {
                if (layers[datasetId]) {
                    return;
                }

                const toggle = document.getElementById(toggleId);
                if (toggle) {
                    toggle.disabled = true;
                    toggle.closest('.layer-item').classList.add('layer-loading');
                }

                fetch(`/api/datasets/${datasetId}/features`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Failed to load layer');
                        }
                        return response.json();
                    })
                    .then(data => {
                        const geojsonLayer = L.geoJSON(data.features, {
                            onEachFeature: onEachFeature,
                            pointToLayer: function(feature, latlng) {
                                return createPointMarker(feature, latlng);
                            },
                            style: function(feature) {
                                return getStyleForGeometryType(feature.geometry?.type);
                            }
                        });

                        layers[datasetId] = geojsonLayer;
                        geojsonLayer.addTo(map);

                        // Fit to layer bounds on first load
                        if (map.getBounds().equals(map.getBounds())) {
                            map.fitBounds(geojsonLayer.getBounds(), { padding: [50, 50] });
                        }

                        const toggle = document.getElementById('layer-toggle-' + datasetId);
                        if (toggle) {
                            toggle.disabled = false;
                            toggle.closest('.layer-item').classList.remove('layer-loading');
                        }
                    })
                    .catch(error => {
                        console.error('Error loading layer:', error);
                        const toggle = document.getElementById(toggleId);
                        if (toggle) {
                            toggle.checked = false;
                            toggle.disabled = false;
                            toggle.closest('.layer-item').classList.remove('layer-loading');
                            toggle.closest('.layer-item').classList.add('layer-error');
                            toggle.closest('.layer-item').setAttribute('data-error', error.message);
                        }
                        alert('Failed to load layer: ' + error.message);
                    });
            }

            function removeLayer(datasetId) {
                if (layers[datasetId]) {
                    map.removeLayer(layers[datasetId]);
                    delete layers[datasetId];
                }
            }

            function createPointMarker(feature, latlng) {
                const geometryType = feature.geometry?.type || 'Point';
                const isPoint = ['Point', 'MultiPoint'].includes(geometryType);
                
                if (isPoint) {
                    return L.circleMarker(latlng, {
                        radius: 6,
                        fillColor: getRandomColor(),
                        color: '#fff',
                        weight: 1,
                        opacity: 1,
                        fillOpacity: 0.8
                    });
                }
                
                // For non-point geometries, use default marker
                return L.marker(latlng, {
                    icon: L.divIcon({
                        className: 'custom-div-icon',
                        html: '<div style="background: ' + getRandomColor() + '; width: 12px; height: 12px; border-radius: 50%; border: 2px solid white;"></div>',
                        iconSize: [12, 12],
                        iconAnchor: [6, 6]
                    })
                });
            }

            function getStyleForGeometryType(geometryType) {
                const baseColor = getRandomColor();
                const styles = {
                    'Point': { color: baseColor, weight: 2, fillOpacity: 0.5, radius: 6 },
                    'MultiPoint': { color: baseColor, weight: 2, fillOpacity: 0.5, radius: 6 },
                    'LineString': { color: baseColor, weight: 3, fillOpacity: 0, dashArray: '5, 10' },
                    'MultiLineString': { color: baseColor, weight: 3, fillOpacity: 0, dashArray: '5, 10' },
                    'Polygon': { color: baseColor, weight: 2, fillOpacity: 0.3 },
                    'MultiPolygon': { color: baseColor, weight: 2, fillOpacity: 0.3 },
                };
                return styles[geometryType] || { color: baseColor, weight: 2, fillOpacity: 0.5 };
            }

            function onEachFeature(feature, layer) {
                if (feature.properties) {
                    let popupContent = '<div class="feature-popup"><h4 class="font-semibold mb-2 text-gray-900">Feature Details</h4>';
                    
                    for (const [key, value] of Object.entries(feature.properties)) {
                        if (value !== null && value !== undefined) {
                            const displayValue = typeof value === 'object' ? JSON.stringify(value) : value;
                            popupContent += `<div class="property-row"><span class="property-key">${escapeHtml(key)}:</span><span class="property-value">${escapeHtml(String(displayValue))}</span></div>`;
                        }
                    }
                    popupContent += '</div>';
                    layer.bindPopup(popupContent, { maxWidth: 300 });
                }
            }

            function getRandomColor() {
                const colors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16'];
                return colors[Math.floor(Math.random() * colors.length)];
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Map controls
            document.getElementById('zoom-to-layers')?.addEventListener('click', function() {
                const layersArray = Object.values(layers);
                if (layersArray.length > 0) {
                    const group = L.featureGroup(layersArray);
                    map.fitBounds(group.getBounds(), { padding: [50, 50] });
                }
            });

            document.getElementById('reset-view')?.addEventListener('click', function() {
                map.setView([31.5, 34.5], 8);
            });

            // Handle feature click for inspection
            map.on('click', function(e) {
                // Close any open popups when clicking on map
            });
        });
    </script>
@endsection