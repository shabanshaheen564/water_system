<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#7a1f1f">
    <title>نظام إدارة المياه ونظم المعلومات الجغرافية - بلدية دير البلح</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .welcome-pattern {
            background-image:
                radial-gradient(circle at 10% 20%, rgba(198,40,40,.08) 0 2px, transparent 2px),
                radial-gradient(circle at 90% 80%, rgba(19,131,160,.08) 0 2px, transparent 2px);
            background-size: 34px 34px;
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800">
    <main class="welcome-pattern min-h-screen flex items-center justify-center px-4 py-10">
        <div class="w-full max-w-5xl">
            <div class="overflow-hidden rounded-3xl bg-white border border-slate-200 shadow-2xl">
                <div class="h-2 bg-gradient-to-l from-red-700 via-green-700 to-cyan-700"></div>

                <div class="grid lg:grid-cols-5">
                    <section class="lg:col-span-3 p-8 sm:p-12 lg:p-14">
                        <div class="flex items-center gap-4 mb-8">
                            <div class="h-20 w-20 rounded-2xl bg-red-700 flex items-center justify-center shadow-lg shadow-red-900/20">
                                <svg class="h-12 w-12 text-white" viewBox="0 0 64 64" fill="none" aria-hidden="true">
                                    <path d="M8 48h48" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
                                    <path d="M14 45V25l12-8 12 8v20" stroke="currentColor" stroke-width="4" stroke-linejoin="round"/>
                                    <path d="M43 45V28l8-5 5 4v18" stroke="currentColor" stroke-width="4" stroke-linejoin="round"/>
                                    <path d="M20 45V32h6v13M31 45V32h6v13" stroke="currentColor" stroke-width="3"/>
                                    <path d="M43 18c4-7 9-8 13-7-2 4-5 7-11 8M44 20c-7-4-11-3-14-1 4 4 8 5 13 4" stroke="#78b84a" stroke-width="3" stroke-linecap="round"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-red-700">دولة فلسطين</p>
                                <h1 class="text-xl sm:text-2xl font-bold text-slate-900">بلدية دير البلح</h1>
                            </div>
                        </div>

                        <div class="mb-8">
                            <p class="text-sm font-semibold text-cyan-700 mb-2">دائرة المياه والصرف الصحي</p>
                            <h2 class="text-3xl sm:text-4xl font-extrabold leading-tight text-slate-900">
                                نظام إدارة المياه ونظم المعلومات الجغرافية
                            </h2>
                            <p class="mt-5 max-w-2xl text-base sm:text-lg leading-8 text-slate-600">
                                منصة مؤسسية لإدارة بيانات المياه، الأصول المكانية، قواعد البيانات الجغرافية،
                                ومتابعة المعلومات التشغيلية ضمن بيئة موحدة وآمنة.
                            </p>
                        </div>

                        <div class="grid sm:grid-cols-3 gap-3 mb-8">
                            <div class="rounded-xl bg-red-50 border border-red-100 p-4">
                                <p class="font-bold text-red-800">بيانات مركزية</p>
                                <p class="mt-1 text-xs text-slate-600">إدارة منظمة للبيانات</p>
                            </div>
                            <div class="rounded-xl bg-green-50 border border-green-100 p-4">
                                <p class="font-bold text-green-800">GIS</p>
                                <p class="mt-1 text-xs text-slate-600">إدارة البيانات المكانية</p>
                            </div>
                            <div class="rounded-xl bg-cyan-50 border border-cyan-100 p-4">
                                <p class="font-bold text-cyan-800">أصول المياه</p>
                                <p class="mt-1 text-xs text-slate-600">طبقات ومعلومات موثقة</p>
                            </div>
                        </div>

                        <a href="{{ route('login') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-red-700 px-7 py-3.5 text-sm font-bold text-white shadow-lg shadow-red-900/20 hover:bg-red-800 focus:outline-none focus:ring-4 focus:ring-red-200 transition">
                            الدخول إلى النظام
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M20 12H4"/></svg>
                        </a>
                    </section>

                    <aside class="lg:col-span-2 bg-slate-900 p-8 sm:p-12 text-white flex flex-col justify-between">
                        <div>
                            <div class="flex items-center gap-2 mb-7">
                                <span class="h-3 w-3 rounded-full bg-red-500"></span>
                                <span class="h-3 w-3 rounded-full bg-green-500"></span>
                                <span class="h-3 w-3 rounded-full bg-cyan-500"></span>
                                <span class="text-xs font-semibold text-slate-300 mr-2">بوابة الموظفين والجهات المخولة</span>
                            </div>
                            <h3 class="text-2xl font-bold leading-9">إدارة أفضل للبيانات، وقرارات أكثر دقة</h3>
                            <p class="mt-4 text-sm leading-7 text-slate-300">
                                تم تصميم النظام ليكون نقطة وصول موحدة لبيانات دائرة المياه والصرف الصحي، مع قابلية التوسع نحو الأعمال التشغيلية والميدانية.
                            </p>
                        </div>

                        <div class="mt-10 space-y-3">
                            <div class="rounded-xl border border-slate-700 bg-slate-800/70 p-4">
                                <p class="text-sm font-semibold">الخرائط والبيانات المكانية</p>
                                <p class="mt-1 text-xs text-slate-400">عرض الطبقات والبيانات الجغرافية ضمن النظام.</p>
                            </div>
                            <div class="rounded-xl border border-slate-700 bg-slate-800/70 p-4">
                                <p class="text-sm font-semibold">إدارة البيانات والصلاحيات</p>
                                <p class="mt-1 text-xs text-slate-400">صلاحيات مؤسسية حسب الدور والمسؤولية.</p>
                            </div>
                            <div class="rounded-xl border border-slate-700 bg-slate-800/70 p-4">
                                <p class="text-sm font-semibold">بيئة قابلة للتوسع</p>
                                <p class="mt-1 text-xs text-slate-400">مصممة لاستيعاب الخدمات التشغيلية المستقبلية.</p>
                            </div>
                        </div>
                    </aside>
                </div>

                <footer class="border-t border-slate-200 px-6 py-4 text-center text-xs text-slate-500">
                    بلدية دير البلح — دائرة المياه والصرف الصحي © {{ date('Y') }}
                </footer>
            </div>
        </div>
    </main>
</body>
</html>
