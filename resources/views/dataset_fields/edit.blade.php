@extends('layouts.app')

@section('title', __('Edit Field'))

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Edit Field') }}</h1>
            <p class="text-gray-600 mt-1">{{ __('Update field configuration for') }} {{ $fieldModel->dataset->display_name }}</p>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <form method="POST" action="{{ route('datasets.fields.update', [$fieldModel->dataset_id, $fieldModel]) }}" class="p-6 space-y-6">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Name') }}</label>
                        <input type="text" name="name" id="name" value="{{ $fieldModel->name }}" required
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-gray-500">{{ __('Alphanumeric and underscores only') }}</p>
                    </div>

                    <div>
                        <label for="display_name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Display Name') }}</label>
                        <input type="text" name="display_name" id="display_name" value="{{ $fieldModel->display_name }}" required
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        @error('display_name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="data_type" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Data Type') }}</label>
                    <select name="data_type" id="data_type" required
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        <option value="">{{ __('Select Data Type') }}</option>
                        <option value="string" {{ $fieldModel->data_type === 'string' ? 'selected' : '' }}>{{ __('String (Text)') }}</option>
                        <option value="integer" {{ $fieldModel->data_type === 'integer' ? 'selected' : '' }}>{{ __('Integer (Whole Number)') }}</option>
                        <option value="decimal" {{ $fieldModel->data_type === 'decimal' ? 'selected' : '' }}>{{ __('Decimal (Floating Point)') }}</option>
                        <option value="boolean" {{ $fieldModel->data_type === 'boolean' ? 'selected' : '' }}>{{ __('Boolean (True/False)') }}</option>
                        <option value="date" {{ $fieldModel->data_type === 'date' ? 'selected' : '' }}>{{ __('Date') }}</option>
                        <option value="datetime" {{ $fieldModel->data_type === 'datetime' ? 'selected' : '' }}>{{ __('DateTime') }}</option>
                        <option value="text" {{ $fieldModel->data_type === 'text' ? 'selected' : '' }}>{{ __('Text (Long Text)') }}</option>
                    </select>
                    @error('data_type')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Required') }}</label>
                        <div class="mt-1 flex items-center">
                            <input type="hidden" name="is_required" value="0">
                            <input type="checkbox" name="is_required" id="is_required" value="1" {{ $fieldModel->is_required ? 'checked' : '' }}
                                class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <label for="is_required" class="ml-2 text-sm text-gray-700">{{ __('Required Field') }}</label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Unique') }}</label>
                        <div class="mt-1 flex items-center">
                            <input type="hidden" name="is_unique" value="0">
                            <input type="checkbox" name="is_unique" id="is_unique" value="1" {{ $fieldModel->is_unique ? 'checked' : '' }}
                                class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <label for="is_unique" class="ml-2 text-sm text-gray-700">{{ __('Unique Values') }}</label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Identifier') }}</label>
                        <div class="mt-1 flex items-center">
                            <input type="hidden" name="is_identifier" value="0">
                            <input type="checkbox" name="is_identifier" id="is_identifier" value="1" {{ $fieldModel->is_identifier ? 'checked' : '' }}
                                class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <label for="is_identifier" class="ml-2 text-sm text-gray-700">{{ __('Primary Identifier') }}</label>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="default_value" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Default Value') }}</label>
                        <input type="text" name="default_value" id="default_value" value="{{ $fieldModel->default_value }}"
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            placeholder="Optional default value">
                    </div>

                    <div>
                        <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Sort Order') }}</label>
                        <input type="number" name="sort_order" id="sort_order" value="{{ $fieldModel->sort_order }}"
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            placeholder="0">
                    </div>
                </div>

                <div>
                    <label for="metadata" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Metadata (JSON)') }}</label>
                    <textarea name="metadata" id="metadata" rows="4"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                        placeholder='{"unit": "meters", "precision": 2}'>{{ $fieldModel->metadata ? json_encode($fieldModel->metadata, JSON_PRETTY_PRINT) : '' }}</textarea>
                    <p class="mt-1 text-xs text-gray-500">{{ __('Optional JSON metadata (e.g. units, precision, etc.)') }}</p>
                </div>

                <div class="mt-6 pt-6 border-t border-gray-200">
                    <div class="flex justify-end space-x-3">
                        <a href="{{ route('datasets.fields.index', $fieldModel->dataset_id) }}" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            {{ __('Cancel') }}
                        </a>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            {{ __('Update Field') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
