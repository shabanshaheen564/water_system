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
                @foreach($spatialDatasets as $dataset)
                    <div class="layer-item p-3 rounded-lg border border-gray-200" data-dataset-id="{{ $dataset->id }}">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <input type="checkbox" 
                                       class="layer-toggle h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500" 
                                       data-dataset-id="{{ $dataset->id }}"
                                       id="layer-toggle-{{ $dataset->id }}"
                                       {{ $loop->first ? 'checked' : '' }}>
                                <label for="layer-toggle-{{ $dataset->id }}" class="cursor-pointer">
                                    <span class="font-medium text-gray-900">{{ $dataset->display_name }}</span>
                                    <span class="ml-2 px-2 py-0.5 text-xs bg-blue-100 text-blue-800 rounded-full">{{ $dataset->geometry_type }}</span>
                                </label>
                            </div>
                            <span class="text-xs text-gray-500">SRID: {{ $dataset->srid ?? 4326 }}</span>
                        </div>
                    </div>
                @endforeach
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
                    toggle.parentElement.classList.add('opacity-50');
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
                                return L.circleMarker(latlng, {
                                    radius: 6,
                                    fillColor: getRandomColor(),
                                    color: '#fff',
                                    weight: 1,
                                    opacity: 1,
                                    fillOpacity: 0.8
                                });
                            },
                            style: function(feature) {
                                return {
                                    color: getRandomColor(),
                                    weight: 2,
                                    fillOpacity: 0.5
                                };
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
                            toggle.parentElement.classList.remove('opacity-50');
                        }
                    })
                    .catch(error => {
                        console.error('Error loading layer:', error);
                        const toggle = document.getElementById(toggleId);
                        if (toggle) {
                            toggle.checked = false;
                            toggle.disabled = false;
                            toggle.parentElement.classList.remove('opacity-50');
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