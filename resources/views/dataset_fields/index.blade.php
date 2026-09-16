@extends('layouts.app')

@section('title', __('Dataset Fields'))

@section('content')
    <div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
        <div class="mb-6 flex items-center justify-between gap-4">
            <div class="min-w-0">
                <h2 class="truncate text-xl font-semibold leading-[1.5] text-ink">{{ __('Fields for') }} {{ $dataset->display_name }}</h2>
                <p class="mt-1 truncate text-sm text-ink-muted ltr-value">{{ $dataset->name }}</p>
            </div>
            @can('datasets.create')
                <a href="{{ route('datasets.fields.create', $dataset) }}" class="shrink-0 rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Add Field') }}</a>
            @endcan
        </div>

        <div class="card-institutional overflow-hidden">
            @if($fields->count() > 0)
                <div class="overflow-x-auto">
                    <table class="table-institutional">
                        <thead><tr>
                            <th>{{ __('Name') }}</th><th>{{ __('Display Name') }}</th><th>{{ __('Type') }}</th><th>{{ __('Required') }}</th><th>{{ __('Unique') }}</th><th>{{ __('Identifier') }}</th><th>{{ __('Sort') }}</th><th>{{ __('Actions') }}</th>
                        </tr></thead>
                        <tbody>
                            @foreach($fields as $field)
                                <tr class="hover:bg-surface-1">
                                    <td><code class="ltr-value text-sm text-ink-secondary">{{ $field->name }}</code></td>
                                    <td class="text-sm text-ink">{{ $field->display_name }}</td>
                                    <td><code class="ltr-value text-xs text-ink-secondary">{{ $field->data_type }}</code></td>
                                    <td>{{ $field->is_required ? __('Yes') : __('No') }}</td>
                                    <td>{{ $field->is_unique ? __('Yes') : __('No') }}</td>
                                    <td>{{ $field->is_identifier ? __('Yes') : __('No') }}</td>
                                    <td class="ltr-value text-sm text-ink-secondary">{{ $field->sort_order }}</td>
                                    <td>
                                        <div class="flex items-center gap-3 text-sm font-medium">
                                            @can('datasets.update')
                                                <a href="{{ route('datasets.fields.edit', [$field->dataset_id, $field]) }}" class="text-brand-600 hover:text-brand-700">{{ __('Edit') }}</a>
                                            @endcan
                                            @can('datasets.delete')
                                                <form method="POST" action="{{ route('datasets.fields.destroy', [$field->dataset_id, $field]) }}" class="inline" onsubmit="return confirm('{{ __('Are you sure?') }}')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="text-danger hover:underline">{{ __('Delete') }}</button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-border px-4 py-3 sm:px-6">{{ $fields->links() }}</div>
            @else
                <div class="p-12 text-center">
                    <p class="text-sm text-ink-muted">{{ __('No fields defined yet') }}</p>
                    <a href="{{ route('datasets.fields.create', $dataset) }}" class="mt-4 inline-block rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Add First Field') }}</a>
                </div>
            @endif
        </div>
    </div>
@endsection
