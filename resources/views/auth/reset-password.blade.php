@extends('layouts.guest')

@section('content')
<div class="mx-auto flex min-h-[calc(100vh-57px)] max-w-md items-center px-4 py-12">
    <div class="w-full">
        <div class="mb-7 text-center">
            <h1 class="text-2xl font-semibold text-ink">تعيين كلمة مرور جديدة</h1>
            <p class="mt-2 text-sm text-ink-secondary">اختر كلمة مرور جديدة لحسابك.</p>
        </div>
        <div class="border border-border bg-white p-6 sm:p-8">
            @if($errors->any())
                <div class="mb-5 border border-danger bg-danger-surface p-3 text-sm text-danger" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div>
                    <label for="email" class="mb-2 block text-sm font-medium text-ink">البريد الإلكتروني</label>
                    <input id="email" name="email" type="email" required value="{{ old('email', $email) }}"
                        class="input-institutional block w-full px-4 py-3 text-ink outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100">
                </div>

                <div>
                    <label for="password" class="mb-2 block text-sm font-medium text-ink">كلمة المرور الجديدة</label>
                    <input id="password" name="password" type="password" required autocomplete="new-password"
                        class="input-institutional block w-full px-4 py-3 text-ink outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100">
                </div>

                <div>
                    <label for="password_confirmation" class="mb-2 block text-sm font-medium text-ink">تأكيد كلمة المرور</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                        class="input-institutional block w-full px-4 py-3 text-ink outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100">
                </div>

                <button type="submit" class="btn-motion w-full rounded-md bg-brand-600 px-4 py-3 text-sm font-medium text-white hover:bg-brand-700">
                    حفظ كلمة المرور الجديدة
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
