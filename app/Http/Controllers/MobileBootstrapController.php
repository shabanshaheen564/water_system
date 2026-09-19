<?php

namespace App\Http\Controllers;

use App\Services\OperationalReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileBootstrapController extends Controller
{
    public function show(Request $request, OperationalReportService $reports): JsonResponse
    {
        $user = $request->user()->loadMissing('roles.permissions');

        return response()->json([
            'app' => [
                'name' => config('app.name', 'Water GIS Management System'),
                'version' => '1.0.0',
                'api_version' => 'v1',
            ],
            'welcome' => [
                'title' => 'نظام إدارة المياه والصرف الصحي',
                'message' => 'مرحباً بك. استخدم التطبيق لمتابعة الشكاوى والمهام الميدانية وتحديث حالتها ومواقعها.',
                'instructions' => [
                    'تأكد من تفعيل الموقع عند تنفيذ مهمة ميدانية.',
                    'يمكن متابعة المهام والعمل عند انقطاع الإنترنت ثم مزامنة التغييرات لاحقاً.',
                    'لا تشارك بيانات الدخول مع أي مستخدم آخر.',
                ],
            ],
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => (bool) $user->is_active,
                'roles' => $user->roles->pluck('name')->values(),
                'permissions' => $user->getAllPermissions()->pluck('name')->sort()->values(),
            ],
            'navigation' => [
                'statistics' => $user->can('reports.view'),
                'complaints' => $user->can('complaints.view'),
                'tasks' => $user->can('tasks.view'),
                'map' => $user->can('gis.view') || $user->can('complaints.view') || $user->can('tasks.view'),
            ],
            'features' => [
                'create_complaint' => $user->can('complaints.create'),
                'update_complaint' => $user->can('complaints.update') || $user->can('complaints.transition'),
                'convert_complaint_to_task' => $user->can('complaints.convert_to_task'),
                'convert_task_to_complaint' => $user->can('tasks.update') && $user->can('complaints.create'),
                'create_task' => $user->can('tasks.create'),
                'update_task' => $user->can('tasks.update') || $user->can('tasks.assign') || $user->can('tasks.transition'),
                'export_reports' => $user->can('reports.export'),
                'offline_sync' => true,
            ],
            'filters' => $reports->filters(),
            'endpoints' => [
                'dashboard' => '/api/reports/summary',
                'complaints' => '/api/complaints',
                'complaint_filters' => '/api/complaints/filters',
                'tasks' => '/api/work-orders',
                'task_filters' => '/api/work-orders/filters',
                'map' => '/api/map/operational',
                'reports_filters' => '/api/reports/filters',
            ],
        ]);
    }
}
