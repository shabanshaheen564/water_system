<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ComplaintImportService
{
    public const TARGET_FIELDS = [
        'complaint_number' => 'رقم الشكوى',
        'title' => 'العنوان',
        'description' => 'الوصف / المشكلة',
        'status' => 'الحالة',
        'priority' => 'الأولوية',
        'contact_name' => 'اسم المواطن',
        'contact_phone' => 'الهاتف',
        'address' => 'العنوان / الموقع',
        'latitude' => 'خط العرض',
        'longitude' => 'خط الطول',
        'assigned_to' => 'المسند إليه',
        'processing_notes' => 'ملاحظات المعالجة',
        'solution' => 'الحل',
    ];

    public function preview(UploadedFile|string $file): array
    {
        $spreadsheet = IOFactory::load($file instanceof UploadedFile ? $file->getRealPath() : $file);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        if ($rows === []) throw new \RuntimeException('ملف الاستيراد فارغ.');

        $headers = array_map(fn ($value) => trim((string) $value), $rows[0]);
        if (count(array_filter($headers, fn ($value) => $value !== '')) === 0) {
            throw new \RuntimeException('الصف الأول في الملف لا يحتوي على أسماء أعمدة.');
        }

        $normalizedHeaders = array_map(fn ($value) => $this->normalize($value), $headers);
        $sampleRows = [];
        foreach (array_slice($rows, 1, 5) as $row) {
            $sampleRows[] = array_map(
                fn ($value) => is_scalar($value) ? (string) $value : '',
                array_pad($row, count($headers), '')
            );
        }

        return [
            'headers' => $headers,
            'sample_rows' => $sampleRows,
            'total_rows' => max(count($rows) - 1, 0),
            'auto_mapping' => $this->buildMapping($normalizedHeaders),
        ];
    }

    public function import(UploadedFile $file, int $userId): array
    {
        return $this->importFile($file->getRealPath(), $userId, null, []);
    }

    public function importStored(string $path, int $userId, array $mapping, array $decisions = []): array
    {
        return $this->importFile($path, $userId, $mapping, $decisions);
    }

    public function analyzeStored(string $path, array $mapping): array
    {
        $rows = $this->readRows($path);
        $suspicious = [];
        $errors = [];
        $newRows = 0;

        foreach (array_slice($rows, 1) as $index => $row) {
            $rowNumber = $index + 2;
            if ($this->emptyRow($row)) continue;

            try {
                $data = $this->mapRow($rows[0], $row, $mapping);
                if (empty($data['title'])) throw new \RuntimeException('العنوان مطلوب.');

                $matches = $this->findPossibleDuplicates($data);
                if ($matches !== []) {
                    $suspicious[] = [
                        'row' => $rowNumber,
                        'data' => $this->reviewData($data),
                        'matches' => $matches,
                        'score' => $matches[0]['score'],
                    ];
                } else {
                    $newRows++;
                }
            } catch (\Throwable $e) {
                $errors[] = ['row' => $rowNumber, 'error' => $e->getMessage()];
            }
        }

        return [
            'total_rows' => max(count($rows) - 1, 0),
            'new_rows' => $newRows,
            'suspicious' => $suspicious,
            'failed' => count($errors),
            'errors' => $errors,
        ];
    }

    private function importFile(string $path, int $userId, ?array $selectedMapping, array $decisions): array
    {
        $rows = $this->readRows($path);
        $headers = array_map(fn ($value) => $this->normalize((string) $value), $rows[0]);

        $map = $selectedMapping ?: $this->buildMapping($headers);
        if (! in_array('title', array_values($map), true)) {
            throw new \RuntimeException('يجب ربط عمود العنوان بحقل العنوان.');
        }

        $created = $skipped = 0;
        $errors = [];

        foreach (array_slice($rows, 1) as $index => $row) {
            $rowNumber = $index + 2;
            if ($this->emptyRow($row)) continue;

            try {
                $data = $this->mapRow($headers, $row, $map);
                if (empty($data['title'])) throw new \RuntimeException('العنوان مطلوب.');

                $matches = $this->findPossibleDuplicates($data);
                if ($matches !== []) {
                    $decision = $decisions[(string) $rowNumber] ?? null;
                    if (! in_array($decision, ['import', 'skip'], true)) {
                        throw new \RuntimeException('يجب تحديد قرار لهذه الشكوى المشكوك بتكرارها.');
                    }
                    if ($decision === 'skip') {
                        $skipped++;
                        continue;
                    }
                }

                $number = ! empty($data['complaint_number']) ? (string) $data['complaint_number'] : null;
                if ($number && Complaint::where('complaint_number', $number)->exists()) {
                    $number = null;
                }

                Complaint::create([
                    'complaint_number' => $number ?: $this->generateNumber(),
                    'title' => (string) $data['title'],
                    'description' => (string) ($data['description'] ?? ''),
                    'status' => $this->normalizeStatus($data['status'] ?? 'open'),
                    'priority' => $this->normalizePriority($data['priority'] ?? 'medium'),
                    'reported_by' => $userId,
                    'assigned_to' => $this->resolveUser($data['assigned_to'] ?? null),
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

    private function readRows(string $path): array
    {
        $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, false);
        if ($rows === []) throw new \RuntimeException('ملف الاستيراد فارغ.');
        return $rows;
    }

    private function mapRow(array $headers, array $row, array $map): array
    {
        $data = [];
        foreach ($headers as $columnIndex => $header) {
            $field = $map[$columnIndex] ?? ($map[$header] ?? null);
            if ($field !== null && array_key_exists($field, self::TARGET_FIELDS)) {
                $data[$field] = is_string($row[$columnIndex] ?? null)
                    ? trim($row[$columnIndex])
                    : ($row[$columnIndex] ?? null);
            }
        }
        return $data;
    }

    private function findPossibleDuplicates(array $data): array
    {
        $number = trim((string) ($data['complaint_number'] ?? ''));
        if ($number !== '') {
            $exact = Complaint::query()->where('complaint_number', $number)->first();
            if ($exact) return [[
                'id' => $exact->id,
                'complaint_number' => $exact->complaint_number,
                'title' => $exact->title,
                'contact_name' => $exact->contact_name,
                'contact_phone' => $exact->contact_phone,
                'address' => $exact->address,
                'status' => $exact->status,
                'score' => 100,
                'reason' => 'رقم الشكوى موجود مسبقاً',
            ]];
        }

        $phone = $this->normalizePhone($data['contact_phone'] ?? null);
        $name = $this->normalizeText($data['contact_name'] ?? null);
        $title = $this->normalizeText($data['title'] ?? null);
        $description = $this->normalizeText($data['description'] ?? null);
        $address = $this->normalizeText($data['address'] ?? null);

        $query = Complaint::query();
        if ($phone !== '') {
            $query->where('contact_phone', $data['contact_phone']);
        } elseif ($name !== '') {
            $query->whereRaw('LOWER(contact_name) = ?', [$name]);
        } else {
            return [];
        }

        $candidates = $query->latest()->limit(20)->get();
        $matches = [];
        foreach ($candidates as $complaint) {
            $score = 0;
            $reasons = [];

            if ($phone !== '' && $phone === $this->normalizePhone($complaint->contact_phone)) {
                $score += 45; $reasons[] = 'نفس رقم الهاتف';
            }
            if ($name !== '' && $name === $this->normalizeText($complaint->contact_name)) {
                $score += 25; $reasons[] = 'نفس اسم المواطن';
            }

            $existingText = $this->normalizeText(($complaint->title ?? '') . ' ' . ($complaint->description ?? ''));
            $incomingText = trim($title . ' ' . $description);
            similar_text($incomingText, $existingText, $textSimilarity);
            if ($textSimilarity >= 55) {
                $score += min(20, (int) round($textSimilarity / 5));
                $reasons[] = 'تشابه في وصف المشكلة';
            }

            if ($address !== '' && $address === $this->normalizeText($complaint->address)) {
                $score += 10; $reasons[] = 'نفس الموقع النصي';
            }

            if ($score >= 60) {
                $matches[] = [
                    'id' => $complaint->id,
                    'complaint_number' => $complaint->complaint_number,
                    'title' => $complaint->title,
                    'contact_name' => $complaint->contact_name,
                    'contact_phone' => $complaint->contact_phone,
                    'address' => $complaint->address,
                    'status' => $complaint->status,
                    'score' => min(100, $score),
                    'reason' => implode(' + ', $reasons),
                ];
            }
        }

        usort($matches, fn ($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($matches, 0, 3);
    }

    private function reviewData(array $data): array
    {
        return [
            'complaint_number' => $data['complaint_number'] ?? null,
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'contact_name' => $data['contact_name'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'address' => $data['address'] ?? null,
        ];
    }

    private function normalizePhone(mixed $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?: '';
    }

    private function normalizeText(mixed $value): string
    {
        $value = mb_strtolower(trim((string) $value));
        return preg_replace('/[\s\-_]+/u', ' ', $value) ?: '';
    }

    private function buildMapping(array $headers): array
    {
        $aliases = [
            'complaint_number' => ['complaint_number','complaint_no','number','رقم الشكوى','رقم_الشكوى'],
            'title' => ['title','subject','complaint_title','العنوان','الموضوع','عنوان الشكوى'],
            'description' => ['description','details','problem','الوصف','التفاصيل','المشكلة'],
            'priority' => ['priority','الأولوية','اولوية'],
            'status' => ['status','الحالة'],
            'contact_name' => ['contact_name','citizen_name','citizen','name','اسم المواطن','المواطن'],
            'contact_phone' => ['contact_phone','phone','mobile','telephone','الهاتف','الجوال','رقم الهاتف'],
            'address' => ['address','location','العنوان المكاني','الموقع','العنوان'],
            'latitude' => ['latitude','lat','y','خط العرض','latitude_wgs84'],
            'longitude' => ['longitude','lon','lng','x','خط الطول','longitude_wgs84'],
            'assigned_to' => ['assigned_to','assigned_user','assignee','المسند إليه','الموظف'],
            'processing_notes' => ['processing_notes','notes','ملاحظات المعالجة','ملاحظات'],
            'solution' => ['solution','resolution','الحل','المعالجة'],
        ];

        $map = [];
        foreach ($headers as $columnIndex => $header) {
            foreach ($aliases as $field => $fieldAliases) {
                if (in_array($header, array_map(fn ($value) => $this->normalize($value), $fieldAliases), true)) {
                    $map[$columnIndex] = $field;
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
        return match ($this->normalize((string) $value)) {
            'open','جديدة','جديد','مفتوحة' => 'open',
            'in_progress','inprogress','قيد_المعالجة' => 'in_progress',
            'resolved','تم_الحل','محلولة' => 'resolved',
            'closed','مغلقة','مغلق' => 'closed',
            'cancelled','canceled','ملغاة' => 'cancelled',
            default => 'open',
        };
    }

    private function normalizePriority(mixed $value): string
    {
        return match ($this->normalize((string) $value)) {
            'low','منخفضة','منخفض' => 'low',
            'high','عالية','عالي' => 'high',
            'urgent','عاجلة','عاجل' => 'urgent',
            default => 'medium',
        };
    }

    private function resolveUser(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') return null;
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
        foreach ($row as $value) if ($value !== null && trim((string) $value) !== '') return false;
        return true;
    }

    private function generateNumber(): string
    {
        $next = DB::selectOne("SELECT nextval('complaints_number_seq') AS next_number")->next_number;
        return 'CMP-' . str_pad($next, 6, '0', STR_PAD_LEFT);
    }
}
