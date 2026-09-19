<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ComplaintImportService
{
    public function import(UploadedFile $file, int $userId): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        if ($rows === []) {
            throw new \RuntimeException('ملف الاستيراد فارغ.');
        }

        $headers = array_map(fn ($value) => trim((string) $value), $rows[0]);
        $headers = array_map(fn ($value) => $this->normalize($value), $headers);

        if (! in_array('title', $headers, true)) {
            throw new \RuntimeException('يجب أن يحتوي الملف على عمود للعُنوان (title/العنوان/الموضوع).');
        }

        $map = $this->buildMapping($headers);
        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach (array_slice($rows, 1) as $index => $row) {
            $rowNumber = $index + 2;
            if ($this->emptyRow($row)) {
                continue;
            }

            try {
                $data = [];
                foreach ($headers as $columnIndex => $header) {
                    $field = $map[$header] ?? null;
                    if ($field !== null) {
                        $data[$field] = is_string($row[$columnIndex] ?? null)
                            ? trim($row[$columnIndex])
                            : ($row[$columnIndex] ?? null);
                    }
                }

                if (empty($data['title'])) {
                    throw new \RuntimeException('العنوان مطلوب.');
                }

                $number = ! empty($data['complaint_number']) ? (string) $data['complaint_number'] : null;
                if ($number && Complaint::where('complaint_number', $number)->exists()) {
                    $skipped++;
                    continue;
                }

                $assignedTo = $this->resolveUser($data['assigned_to'] ?? null);
                $status = $this->normalizeStatus($data['status'] ?? 'open');
                $priority = $this->normalizePriority($data['priority'] ?? 'medium');

                Complaint::create([
                    'complaint_number' => $number ?: $this->generateNumber(),
                    'title' => (string) $data['title'],
                    'description' => (string) ($data['description'] ?? ''),
                    'status' => $status,
                    'priority' => $priority,
                    'reported_by' => $userId,
                    'assigned_to' => $assignedTo,
                    'contact_name' => $data['contact_name'] ?? null,
                    'contact_phone' => $data['contact_phone'] ?? null,
                    'address' => $data['address'] ?? null,
                    'latitude' => $this->numberOrNull($data['latitude'] ?? null),
                    'longitude' => $this->numberOrNull($data['longitude'] ?? null),
                    'processing_notes' => $data['processing_notes'] ?? null,
                    'solution' => $data['solution'] ?? null,
                ]);
                $created++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $rowNumber, 'error' => $e->getMessage()];
            }
        }

        return [
            'total_rows' => max(count($rows) - 1, 0),
            'created' => $created,
            'skipped' => $skipped,
            'failed' => count($errors),
            'errors' => $errors,
        ];
    }

    private function buildMapping(array $headers): array
    {
        $aliases = [
            'complaint_number' => ['complaint_number', 'complaint_no', 'number', 'رقم الشكوى', 'رقم_الشكوى'],
            'title' => ['title', 'subject', 'complaint_title', 'العنوان', 'الموضوع', 'عنوان الشكوى'],
            'description' => ['description', 'details', 'problem', 'الوصف', 'التفاصيل', 'المشكلة'],
            'priority' => ['priority', 'الأولوية', 'اولوية'],
            'status' => ['status', 'الحالة'],
            'contact_name' => ['contact_name', 'citizen_name', 'citizen', 'name', 'اسم المواطن', 'المواطن'],
            'contact_phone' => ['contact_phone', 'phone', 'mobile', 'telephone', 'الهاتف', 'الجوال', 'رقم الهاتف'],
            'address' => ['address', 'location', 'العنوان المكاني', 'الموقع', 'العنوان'],
            'latitude' => ['latitude', 'lat', 'y', 'خط العرض', 'latitude_wgs84'],
            'longitude' => ['longitude', 'lon', 'lng', 'x', 'خط الطول', 'longitude_wgs84'],
            'assigned_to' => ['assigned_to', 'assigned_user', 'assignee', 'المسند إليه', 'الموظف'],
            'processing_notes' => ['processing_notes', 'notes', 'ملاحظات المعالجة', 'ملاحظات'],
            'solution' => ['solution', 'resolution', 'الحل', 'المعالجة'],
        ];

        $map = [];
        foreach ($headers as $header) {
            foreach ($aliases as $field => $fieldAliases) {
                if (in_array($header, array_map(fn ($value) => $this->normalize($value), $fieldAliases), true)) {
                    $map[$header] = $field;
                    break;
                }
            }
        }

        return $map;
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(preg_replace('/[\s\-_]+/u', '_', trim($value)));
    }

    private function normalizeStatus(mixed $value): string
    {
        $value = $this->normalize((string) $value);
        return match ($value) {
            'open', 'جديدة', 'جديد', 'مفتوحة' => 'open',
            'in_progress', 'inprogress', 'قيد_المعالجة', 'قيد_المعالجة' => 'in_progress',
            'resolved', 'تم_الحل', 'محلولة' => 'resolved',
            'closed', 'مغلقة', 'مغلق' => 'closed',
            'cancelled', 'canceled', 'ملغاة', 'ملغاة' => 'cancelled',
            default => 'open',
        };
    }

    private function normalizePriority(mixed $value): string
    {
        $value = $this->normalize((string) $value);
        return match ($value) {
            'low', 'منخفضة', 'منخفض' => 'low',
            'high', 'عالية', 'عالي' => 'high',
            'urgent', 'عاجلة', 'عاجل' => 'urgent',
            default => 'medium',
        };
    }

    private function resolveUser(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $value = trim((string) $value);
        $user = is_numeric($value)
            ? User::query()->whereKey((int) $value)->where('is_active', true)->first()
            : User::query()->where('email', $value)->where('is_active', true)->first();

        return $user?->id;
    }

    private function numberOrNull(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') return null;
        if (! is_numeric($value)) throw new \RuntimeException('قيمة الإحداثيات غير رقمية.');
        return (float) $value;
    }

    private function emptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') return false;
        }
        return true;
    }

    private function generateNumber(): string
    {
        $next = \Illuminate\Support\Facades\DB::selectOne("SELECT nextval('complaints_number_seq') AS next_number")->next_number;
        return 'CMP-' . str_pad($next, 6, '0', STR_PAD_LEFT);
    }
}
