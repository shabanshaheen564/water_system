@extends('layouts.app')

@section('title', __('Dataset Details'))

@section('content')
    <div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
        <div class="mb-6 flex items-center justify-between gap-4">
            <div class="min-w-0">
                <h2 class="truncate text-xl font-semibold leading-[1.5] text-ink">{{ $dataset->display_name }}</h2>
                <p class="mt-1 truncate text-sm text-ink-muted ltr-value">{{ $dataset->name }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-3">
                @can('datasets.update')
                    <a href="{{ route('datasets.edit', $dataset) }}" class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm font-medium text-ink hover:bg-surface-1">{{ __('Edit') }}</a>
                @endcan
                @if($dataset->is_spatial && $dataset->is_active)
                    <a href="{{ route('map.index') }}?dataset={{ $dataset->id }}" class="btn-motion rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('View on Map') }}</a>
                @endif
            </div>
        </div>

        <div data-enter class="card-institutional overflow-hidden">
            <div class="border-b border-border p-6">
                <dl class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-4">
                    @foreach([
                        ['label' => __('Name'), 'value' => $dataset->name, 'code' => true],
                        ['label' => __('Display Name'), 'value' => $dataset->display_name],
                        ['label' => __('Type'), 'value' => $dataset->dataset_type === 'official_layer' ? __('Official Layer') : __('Additional Table')],
                        ['label' => __('Status'), 'value' => $dataset->is_active ? __('Active') : __('Inactive')],
                        ['label' => __('Spatial'), 'value' => $dataset->is_spatial ? __('Yes') : __('No')],
                        ['label' => __('Geometry Type'), 'value' => $dataset->geometry_type ?? '—', 'code' => true],
                        ['label' => __('SRID'), 'value' => $dataset->srid ?? '—', 'code' => true],
                        ['label' => __('Source Name'), 'value' => $dataset->source_name ?? '—'],
                        ['label' => __('Source Format'), 'value' => $dataset->source_format ?? '—'],
                        ['label' => __('Records'), 'value' => number_format($recordsCount ?? 0)],
                        ['label' => __('GIS Features'), 'value' => number_format($featuresCount ?? 0)],
                        ['label' => __('Fields'), 'value' => $dataset->fields->count()],
                        ['label' => __('Created By'), 'value' => $dataset->createdBy->name ?? '—'],
                        ['label' => __('Created At'), 'value' => $dataset->created_at->format('Y-m-d H:i'), 'code' => true],
                    ] as $item)
                        <div>
                            <dt class="text-xs font-medium text-ink-muted">{{ $item['label'] }}</dt>
                            <dd class="mt-1 text-sm text-ink {{ !empty($item['code']) ? 'ltr-value' : '' }}">{{ $item['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
            @if($dataset->description)
                <div class="p-6">
                    <h3 class="mb-2 text-base font-semibold text-ink">{{ __('Description') }}</h3>
                    <p class="whitespace-pre-wrap text-sm text-ink-secondary">{{ $dataset->description }}</p>
                </div>
            @endif
        </div>

        @if($dataset->is_spatial && $featuresCount > 0)
            <section data-enter class="card-institutional mt-6 overflow-hidden">
                <div class="flex items-center justify-between gap-4 border-b border-border px-6 py-4">
                    <h3 class="text-base font-semibold text-ink">{{ __('GIS Features') }}</h3>
                    <a href="{{ route('map.index') }}?dataset={{ $dataset->id }}" class="btn-motion rounded-md bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('View on Map') }}</a>
                </div>
                <p class="px-6 py-4 text-sm text-ink-secondary">{{ $featuresCount }} {{ __('features on map') }}</p>
            </section>
        @endif

        <section data-enter class="card-institutional mt-6 overflow-hidden">
            <div class="border-b border-border px-6 py-4">
                <h3 class="text-base font-semibold text-ink">{{ __('Fields') }}</h3>
            </div>
            @if($dataset->fields->count() > 0)
                <div class="overflow-x-auto">
                    <table class="table-institutional">
                        <thead><tr>
                            <th>{{ __('Name') }}</th><th>{{ __('Display Name') }}</th><th>{{ __('Type') }}</th>
                            <th>{{ __('Required') }}</th><th>{{ __('Unique') }}</th><th>{{ __('Identifier') }}</th><th>{{ __('Sort') }}</th>
                        </tr></thead>
                        <tbody>
                            @foreach($dataset->fields->sortBy('sort_order') as $field)
                                <tr class="hover:bg-surface-1">
                                    <td><code class="ltr-value text-sm text-ink-secondary">{{ $field->name }}</code></td>
                                    <td class="text-sm text-ink">{{ $field->display_name }}</td>
                                    <td><code class="ltr-value text-xs text-ink-secondary">{{ $field->data_type }}</code></td>
                                    <td>{{ $field->is_required ? __('Yes') : __('No') }}</td>
                                    <td>{{ $field->is_unique ? __('Yes') : __('No') }}</td>
                                    <td>{{ $field->is_identifier ? __('Yes') : __('No') }}</td>
                                    <td class="ltr-value text-sm text-ink-secondary">{{ $field->sort_order }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-8 text-center">
                    <p class="text-sm text-ink-muted">{{ __('No fields defined yet') }}</p>
                    @can('datasets.create')
                        <a href="{{ route('datasets.fields.create', $dataset) }}" class="btn-motion mt-4 inline-block rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Add First Field') }}</a>
                    @endcan
                </div>
            @endif
        </section>
    </div>
@endsection
