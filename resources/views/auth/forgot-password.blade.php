@extends('layouts.guest')

@section('content')
<div class="mx-auto flex min-h-[calc(100vh-57px)] max-w-md items-center px-4 py-12">
    <div class="w-full">
        <div class="mb-7 text-center">
            <h1 class="text-2xl font-semibold text-ink">إعادة تعيين كلمة المرور</h1>
            <p class="mt-2 text-sm text-ink-secondary">أدخل بريدك الإلكتروني لإرسال رابط آمن لإعادة تعيين كلمة المرور.</p>
        </div>
        <div class="border border-border bg-white p-6 sm:p-8">
            @if(session('status'))
                <div class="mb-5 border border-green-200 bg-green-50 p-3 text-sm text-green-700" role="status">{{ session('status') }}</div>
            @endif

            @if($errors->any())
                <div class="mb-5 border border-danger bg-danger-surface p-3 text-sm text-danger" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
                @csrf
                <div>
                    <label for="email" class="mb-2 block text-sm font-medium text-ink">البريد الإلكتروني</label>
                    <input id="email" name="email" type="email" required autofocus value="{{ old('email') }}"
                        class="input-institutional block w-full px-4 py-3 text-ink outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100"
                        placeholder="name@example.com">
                </div>
                <button type="submit" class="btn-motion w-full rounded-md bg-brand-600 px-4 py-3 text-sm font-medium text-white hover:bg-brand-700">
                    إرسال رابط إعادة التعيين
                </button>
            </form>

            <div class="mt-5 text-center">
                <a href="{{ route('login') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">العودة إلى تسجيل الدخول</a>
            </div>
        </div>
    </div>
</div>
@endsection
