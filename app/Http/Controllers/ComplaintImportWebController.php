<?php

namespace App\Http\Controllers;

use App\Services\ComplaintImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ComplaintImportWebController extends Controller
{
    public function create(): View
    {
        return view('complaints.import');
    }

    public function preview(Request $request, ComplaintImportService $service): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ]);

        $file = $request->file('file');
        $token = (string) Str::uuid();
        $extension = strtolower($file->getClientOriginalExtension() ?: 'xlsx');
        $path = 'complaint-imports/' . $token . '.' . $extension;

        Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

        try {
            $preview = $service->preview(Storage::disk('local')->path($path));
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        session()->put('complaint_import.' . $token, [
            'path' => $path,
            'preview' => $preview,
        ]);

        return redirect()->route('complaints.import.mapping', ['token' => $token]);
    }

    public function mapping(string $token): View|RedirectResponse
    {
        if (! Str::isUuid($token)) {
            return redirect()->route('complaints.import')->withErrors(['file' => 'رابط الاستيراد غير صالح. اختر الملف مرة أخرى.']);
        }

        $import = session('complaint_import.' . $token);
        $path = is_array($import) ? ($import['path'] ?? null) : $import;
        $preview = is_array($import) ? ($import['preview'] ?? null) : null;

        if (! $path || ! Storage::disk('local')->exists($path) || ! is_array($preview)) {
            return redirect()->route('complaints.import')->withErrors(['file' => 'انتهت صلاحية ملف الاستيراد. اختر الملف مرة أخرى.']);
        }

        return view('complaints.import-mapping', [
            'token' => $token,
            'headers' => $preview['headers'],
            'sampleRows' => $preview['sample_rows'],
            'totalRows' => $preview['total_rows'],
            'autoMapping' => $preview['auto_mapping'],
            'targetFields' => ComplaintImportService::TARGET_FIELDS,
        ]);
    }

    public function store(Request $request, ComplaintImportService $service): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'uuid'],
            'mapping' => ['required', 'array'],
        ]);

        $token = $request->string('token')->toString();
        $path = session('complaint_import.' . $token);

        if (! $path || ! Storage::disk('local')->exists($path)) {
            return redirect()->route('complaints.import')->withErrors(['file' => 'انتهت صلاحية ملف الاستيراد. اختر الملف مرة أخرى.']);
        }

        $mapping = [];
        foreach ($request->input('mapping', []) as $columnIndex => $field) {
            $columnIndex = (int) $columnIndex;
            $field = (string) $field;
            if ($field !== '' && array_key_exists($field, ComplaintImportService::TARGET_FIELDS)) {
                $mapping[$columnIndex] = $field;
            }
        }

        if (! in_array('title', $mapping, true)) {
            return back()->withErrors(['mapping' => 'يجب ربط أحد أعمدة الملف بحقل العنوان.']);
        }

        if (count($mapping) !== count(array_unique($mapping))) {
            return back()->withErrors(['mapping' => 'لا يمكن ربط أكثر من عمود بنفس الحقل.']);
        }

        try {
            $result = $service->importStored(Storage::disk('local')->path($path), $request->user()->id, $mapping);
        } catch (\Throwable $e) {
            return back()->withErrors(['mapping' => $e->getMessage()]);
        } finally {
            Storage::disk('local')->delete($path);
            session()->forget('complaint_import.' . $token);
        }

        return redirect()->route('complaints.import')->with([
            'import_result' => $result,
            'success' => "تم الاستيراد: {$result['created']} شكوى، تم تجاوز {$result['skipped']}، وفشل {$result['failed']}.",
        ]);
    }
}
