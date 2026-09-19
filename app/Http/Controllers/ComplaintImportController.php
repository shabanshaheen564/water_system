<?php

namespace App\Http\Controllers;

use App\Services\ComplaintImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ComplaintImportController extends Controller
{
    public function preview(Request $request, ComplaintImportService $service): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
            'mapping' => ['nullable'],
        ]);

        $file = $request->file('file');
        $token = (string) Str::uuid();
        $extension = strtolower($file->getClientOriginalExtension() ?: 'xlsx');
        $path = 'complaint-imports/api/' . $token . '.' . $extension;
        Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

        try {
            $preview = $service->preview(Storage::disk('local')->path($path));
            $mapping = $this->parseMapping($request->input('mapping'), $preview['auto_mapping']);
            $analysis = $service->analyzeStored(Storage::disk('local')->path($path), $mapping);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            return response()->json(['message' => $e->getMessage()], 422);
        }

        Cache::put('complaint_import_api.' . $token, [
            'path' => $path,
            'mapping' => $mapping,
        ], now()->addHour());

        return response()->json([
            'token' => $token,
            'preview' => $preview,
            'analysis' => $analysis,
            'next' => [
                'method' => 'POST',
                'endpoint' => '/api/complaints/import/finalize',
                'body' => [
                    'token' => $token,
                    'decisions' => 'required for each suspicious row: { "2": "import", "5": "skip" }',
                ],
            ],
        ]);
    }

    public function finalize(Request $request, ComplaintImportService $service): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'uuid'],
            'decisions' => ['required', 'array'],
        ]);

        $token = $request->string('token')->toString();
        $state = Cache::get('complaint_import_api.' . $token);

        if (! is_array($state) || empty($state['path']) || empty($state['mapping'])) {
            return response()->json(['message' => 'انتهت صلاحية جلسة الاستيراد. أعد فحص الملف.'], 410);
        }

        $path = $state['path'];
        if (! Storage::disk('local')->exists($path)) {
            Cache::forget('complaint_import_api.' . $token);
            return response()->json(['message' => 'ملف الاستيراد المؤقت غير موجود.'], 410);
        }

        $decisions = [];
        foreach ($request->input('decisions', []) as $row => $decision) {
            if (in_array($decision, ['import', 'skip'], true)) {
                $decisions[(string) $row] = $decision;
            }
        }

        try {
            $result = $service->importStored(
                Storage::disk('local')->path($path),
                $request->user()->id,
                $state['mapping'],
                $decisions
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => 'تعذر تنفيذ الاستيراد: ' . $e->getMessage()], 422);
        }

        Storage::disk('local')->delete($path);
        Cache::forget('complaint_import_api.' . $token);

        return response()->json([
            'message' => 'تم تنفيذ الاستيراد حسب القرارات.',
            'result' => $result,
        ]);
    }

    private function parseMapping(mixed $value, array $fallback): array
    {
        if ($value === null || $value === '') return $fallback;

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (! is_array($value)) throw new \InvalidArgumentException('صيغة mapping غير صحيحة.');

        $mapping = [];
        foreach ($value as $column => $field) {
            $column = (int) $column;
            $field = (string) $field;
            if ($field !== '' && array_key_exists($field, ComplaintImportService::TARGET_FIELDS)) {
                $mapping[$column] = $field;
            }
        }

        if (! in_array('title', $mapping, true)) {
            throw new \InvalidArgumentException('يجب ربط عمود العنوان بحقل العنوان.');
        }

        if (count($mapping) !== count(array_unique($mapping))) {
            throw new \InvalidArgumentException('لا يمكن ربط أكثر من عمود بنفس الحقل.');
        }

        return $mapping;
    }
}
