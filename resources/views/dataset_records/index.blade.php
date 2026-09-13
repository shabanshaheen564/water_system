@extends('layouts.app')
@section('title', __('Records'))
@section('content')
<div class="p-4 sm:p-6 lg:p-8">
    <div class="mb-6 flex items-center justify-between gap-4">
        <div><h1 class="text-2xl font-bold text-gray-900">{{ $dataset->display_name }} — {{ __('Records') }}</h1><p class="text-gray-600 mt-1">{{ $dataset->name }}</p></div>
        @can('datasets.create')<a href="{{ route('datasets.records.create', $dataset) }}" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">{{ __('Add Record') }}</a>@endcan
    </div>
    @if(session('success'))<div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-800">{{ $errors->first() }}</div>@endif
    <form method="GET" class="mb-4 flex gap-2"><input name="search" value="{{ $search }}" placeholder="{{ __('Search records...') }}" class="flex-1 rounded-md border-gray-300 shadow-sm"><button class="px-4 py-2 bg-gray-800 text-white rounded-md">{{ __('Search') }}</button></form>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>@foreach($dataset->fields as $field)<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ $field->display_name }}</th>@endforeach<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Actions') }}</th></tr></thead>
            <tbody class="divide-y divide-gray-200">@forelse($records as $record)<tr class="hover:bg-gray-50"><td class="px-4 py-3 text-sm font-mono">{{ $record->identifier_value ?? $record->id }}</td>@foreach($dataset->fields as $field)<td class="px-4 py-3 text-sm text-gray-700">{{ is_bool($record->values[$field->name] ?? null) ? (($record->values[$field->name] ?? false) ? __('Yes') : __('No')) : ($record->values[$field->name] ?? '—') }}</td>@endforeach<td class="px-4 py-3 whitespace-nowrap">@can('datasets.update')<a class="text-blue-600 mr-3" href="{{ route('datasets.records.edit', [$dataset, $record]) }}">{{ __('Edit') }}</a>@endcan @can('datasets.delete')<form class="inline" method="POST" action="{{ route('datasets.records.destroy', [$dataset, $record]) }}">@csrf @method('DELETE')<button onclick="return confirm('{{ __('Delete this record?') }}')" class="text-red-600">{{ __('Delete') }}</button></form>@endcan</td></tr>@empty<tr><td colspan="{{ $dataset->fields->count()+2 }}" class="px-4 py-10 text-center text-gray-500">{{ __('No records found.') }}</td></tr>@endforelse</tbody>
        </table>
    </div>
    <div class="mt-4">{{ $records->links() }}</div>
</div>
@endsection
