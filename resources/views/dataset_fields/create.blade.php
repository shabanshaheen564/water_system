@extends('layouts.app')

@section('title', __('Create Field'))

@section('content')
    <div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
        <div class="mb-6">
            <h2 class="text-xl font-semibold leading-[1.5] text-ink">{{ __('Create Field for') }} {{ $dataset->display_name }}</h2>
            <p class="mt-1 text-sm text-ink-secondary">{{ __('Add a new field to this dataset') }}</p>
        </div>
        <div data-enter class="card-institutional overflow-hidden">
            <form method="POST" action="{{ route('datasets.fields.store', $dataset) }}" class="space-y-6 p-6">
                @csrf
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <label for="name" class="mb-1 block text-sm font-medium text-ink">{{ __('Name') }}</label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" required class="input-institutional mt-1 block w-full text-sm" placeholder="well_id, depth, status" dir="ltr">
                        @error('name')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                        <p class="mt-1 text-xs text-ink-muted">{{ __('Alphanumeric and underscores only') }}</p>
                    </div>
                    <div>
                        <label for="display_name" class="mb-1 block text-sm font-medium text-ink">{{ __('Display Name') }}</label>
                        <input type="text" name="display_name" id="display_name" value="{{ old('display_name') }}" required class="input-institutional mt-1 block w-full text-sm">
                        @error('display_name')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label for="data_type" class="mb-1 block text-sm font-medium text-ink">{{ __('Data Type') }}</label>
                    <select name="data_type" id="data_type" required class="input-institutional mt-1 block w-full text-sm">
                        <option value="">{{ __('Select Data Type') }}</option>
                        <option value="string">{{ __('String (Text)') }}</option>
                        <option value="integer">{{ __('Integer (Whole Number)') }}</option>
                        <option value="decimal">{{ __('Decimal (Floating Point)') }}</option>
                        <option value="boolean">{{ __('Boolean (True/False)') }}</option>
                        <option value="date">{{ __('Date') }}</option>
                        <option value="datetime">{{ __('DateTime') }}</option>
                        <option value="text">{{ __('Text (Long Text)') }}</option>
                    </select>
                    @error('data_type')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    @foreach([['is_required','Required','Required Field'],['is_unique','Unique','Unique Values'],['is_identifier','Identifier','Primary Identifier']] as [$field,$label,$caption])
                        <div>
                            <label class="mb-1 block text-sm font-medium text-ink">{{ __($label) }}</label>
                            <div class="mt-2 flex items-center gap-2">
                                <input type="hidden" name="{{ $field }}" value="0">
                                <input type="checkbox" name="{{ $field }}" id="{{ $field }}" value="1" {{ old($field) ? 'checked' : '' }} class="h-4 w-4 rounded border-border-strong text-brand-600 focus:ring-brand-600">
                                <label for="{{ $field }}" class="text-sm text-ink-secondary">{{ __($caption) }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <label for="default_value" class="mb-1 block text-sm font-medium text-ink">{{ __('Default Value') }}</label>
                        <input type="text" name="default_value" id="default_value" value="{{ old('default_value') }}" class="input-institutional mt-1 block w-full text-sm">
                    </div>
                    <div>
                        <label for="sort_order" class="mb-1 block text-sm font-medium text-ink">{{ __('Sort Order') }}</label>
                        <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order') }}" class="input-institutional mt-1 block w-full text-sm" dir="ltr">
                    </div>
                </div>
                <div>
                    <label for="metadata" class="mb-1 block text-sm font-medium text-ink">{{ __('Metadata (JSON)') }}</label>
                    <textarea name="metadata" id="metadata" rows="4" class="input-institutional mt-1 block w-full text-sm" dir="ltr" placeholder='{"unit":"meters","precision":2}'></textarea>
                    <p class="mt-1 text-xs text-ink-muted">{{ __('Optional JSON metadata (e.g. units, precision, etc.)') }}</p>
                </div>
                <div class="flex justify-end gap-3 border-t border-border pt-6">
                    <a href="{{ route('datasets.fields.index', $dataset) }}" class="rounded-md border border-border-strong bg-white px-4 py-2 text-sm font-medium text-ink hover:bg-surface-1">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn-motion rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Create Field') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection