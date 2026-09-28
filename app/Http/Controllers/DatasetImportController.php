<?php

namespace App\Http\Controllers;

use App\Http\Requests\DatasetImport\StoreDatasetImportRequest;
use App\Models\Dataset;
use App\Models\DatasetImport;
use App\Services\DatasetImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DatasetImportController extends Controller
{
    public function __construct(private readonly DatasetImportService $importService) {}

    public function index(Request $request, Dataset $dataset): JsonResponse
    {
        $query = DatasetImport::where('dataset_id', $dataset->id)->with(['importedBy:id,name,email']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $imports = $query->orderBy('created_at', 'desc')->paginate();

        return response()->json([
            'data' => $imports->getCollection()->map(fn ($import) => $this->formatImport($import))->values(),
            'links' => [
                'first' => $imports->url(1),
                'last' => $imports->url($imports->lastPage()),
                'prev' => $imports->previousPageUrl(),
                'next' => $imports->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $imports->currentPage(),
                'from' => $imports->firstItem(),
                'last_page' => $imports->lastPage(),
                'path' => $imports->path(),
                'per_page' => $imports->perPage(),
                'to' => $imports->lastItem(),
                'total' => $imports->total(),
            ],
        ]);
    }

    public function show(Dataset $dataset, DatasetImport $import): JsonResponse
    {
        abort_unless($import->dataset_id === $dataset->id, 404);
        $import->load(['importedBy:id,name,email']);

        return response()->json($this->formatImport($import));
    }

    public function store(StoreDatasetImportRequest $request, Dataset $dataset): JsonResponse
    {
        $validated = $request->validated();
        $file = $request->file('file');

        $import = DatasetImport::create([
            'dataset_id' => $dataset->id,
            'original_filename' => $file->getClientOriginalName(),
            'source_format' => strtolower($file->getClientOriginalExtension()),
            'imported_by' => $request->user()->id,
            'started_at' => now(),
            'status' => 'processing',
            'total_rows' => 0,
            'successful_rows' => 0,
            'failed_rows' => 0,
        ]);

        $this->importService->import($import, $file, $validated['column_mapping'], $dataset);

        return response()->json($this->formatImport($import->fresh()), 201);
    }

    private function formatImport(DatasetImport $import): array
    {
        return [
            'id' => $import->id,
            'dataset_id' => $import->dataset_id,
            'original_filename' => $import->original_filename,
            'source_format' => $import->source_format,
            'imported_by' => $import->importedBy ? [
                'id' => $import->importedBy->id,
                'name' => $import->importedBy->name,
                'email' => $import->importedBy->email,
            ] : null,
            'started_at' => $import->started_at?->toISOString(),
            'completed_at' => $import->completed_at?->toISOString(),
            'status' => $import->status,
            'total_rows' => $import->total_rows,
            'successful_rows' => $import->successful_rows,
            'failed_rows' => $import->failed_rows,
            'error_summary' => $import->error_summary,
            'created_at' => $import->created_at?->toISOString(),
            'updated_at' => $import->updated_at?->toISOString(),
        ];
    }
}
