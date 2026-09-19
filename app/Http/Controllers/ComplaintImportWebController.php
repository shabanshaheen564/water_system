<?php

namespace App\Http\Controllers;

use App\Services\ComplaintImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ComplaintImportWebController extends Controller
{
    public function create(): View
    {
        return view('complaints.import');
    }

    public function store(Request $request, ComplaintImportService $service): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ]);

        try {
            $result = $service->import($request->file('file'), $request->user()->id);
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => $e->getMessage()])->withInput();
        }

        $message = "تم الاستيراد: {$result['created']} شكوى، تم تجاوز {$result['skipped']}، وفشل {$result['failed']}.";
        if ($result['failed'] > 0) {
            $message .= ' راجع تفاصيل الأخطاء أدناه.';
        }

        return redirect()->route('complaints.import')->with([
            'import_result' => $result,
            'success' => $message,
        ]);
    }
}
