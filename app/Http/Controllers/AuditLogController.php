<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::with(['user:id,name,email','dataset:id,name,display_name'])->latest();

        foreach (['action','user_id','dataset_id','auditable_type','auditable_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        $logs = $query->paginate(min(max($request->integer('per_page', 50), 1), 200));

        return response()->json([
            'data' => $logs->items(),
            'links' => [
                'first' => $logs->url(1),
                'last' => $logs->url($logs->lastPage()),
                'prev' => $logs->previousPageUrl(),
                'next' => $logs->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
