<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\WorkOrder;
use App\Services\OperationalReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends Controller
{
    public function summary(Request $request, OperationalReportService $reports): JsonResponse
    {
        return response()->json($reports->summary($request));
    }

    public function filters(OperationalReportService $reports): JsonResponse
    {
        return response()->json($reports->filters());
    }

    public function exportComplaints(Request $request, OperationalReportService $reports)
    {
        $format = strtolower((string) $request->input('format', 'xlsx'));
        abort_unless(in_array($format, ['xlsx', 'csv'], true), 422, 'صيغة التصدير غير مدعومة.');

        $items = $reports->complaintQuery($request)->orderByDesc('created_at')->limit(10000)->get();
        return $this->spreadsheetResponse($items->map(fn ($c) => [
            $c->complaint_number, $c->title, $c->description, $c->status, $c->priority,
            $c->contact_name, $c->contact_phone, $c->address, $c->latitude, $c->longitude,
            optional($c->assignedTo)->name, optional($c->created_at)?->toDateTimeString(),
        ])->all(), [
            'رقم الشكوى','العنوان','الوصف','الحالة','الأولوية','المواطن','الهاتف','العنوان',
            'خط العرض','خط الطول','المسند إليه','تاريخ الإنشاء'
        ], $format, 'complaints');
    }

    public function exportWorkOrders(Request $request, OperationalReportService $reports)
    {
        $format = strtolower((string) $request->input('format', 'xlsx'));
        abort_unless(in_array($format, ['xlsx', 'csv'], true), 422, 'صيغة التصدير غير مدعومة.');

        $items = $reports->workOrderQuery($request)->orderByDesc('created_at')->limit(10000)->get();
        return $this->spreadsheetResponse($items->map(fn ($w) => [
            $w->work_order_number, $w->title, $w->description, $w->status, $w->priority,
            optional($w->assignedTo)->name, $w->latitude, $w->longitude,
            $w->complaints->pluck('complaint_number')->implode(', '), optional($w->created_at)?->toDateTimeString(),
            optional($w->started_at)?->toDateTimeString(), optional($w->completed_at)?->toDateTimeString(),
        ])->all(), [
            'رقم المهمة','العنوان','الوصف','الحالة','الأولوية','المسند إليه','خط العرض','خط الطول',
            'الشكاوى المرتبطة','تاريخ الإنشاء','تاريخ البدء','تاريخ الإنجاز'
        ], $format, 'work-orders');
    }

    public function complaintPdf(Complaint $complaint)
    {
        $complaint->load(['reportedBy:id,name,email','assignedTo:id,name,email','processedBy:id,name,email','workOrders']);
        return Pdf::loadView('reports.complaint', ['complaint' => $complaint])
            ->setPaper('a4', 'portrait')
            ->download('complaint-'.$complaint->complaint_number.'.pdf');
    }

    public function workOrderPdf(WorkOrder $workOrder)
    {
        $workOrder->load(['assignedTo:id,name,email','createdBy:id,name,email','complaints']);
        return Pdf::loadView('reports.work-order', ['workOrder' => $workOrder])
            ->setPaper('a4', 'portrait')
            ->download('work-order-'.$workOrder->work_order_number.'.pdf');
    }

    private function spreadsheetResponse($rows, array $headers, string $format, string $prefix)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([$headers], null, 'A1');
        if ($rows !== []) $sheet->fromArray($rows, null, 'A2');
        $sheet->freezePane('A2');
        $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getFont()->setBold(true);
        $sheet->setAutoFilter('A1:'.$sheet->getHighestColumn().$sheet->getHighestRow());

        $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
        if ($writer instanceof Csv) {
            $writer->setUseBOM(true);
            $writer->setOutputEncoding('UTF-8');
        }
        $extension = $format === 'csv' ? 'csv' : 'xlsx';
        $mime = $format === 'csv' ? 'text/csv' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $prefix.'-'.now()->format('Ymd-His').'.'.$extension, [
            'Content-Type' => $mime,
            'Cache-Control' => 'no-store',
        ]);
    }
}
